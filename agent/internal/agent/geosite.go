package agent

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
)

func downloadGeosite(ctx context.Context, client *http.Client, dataURL, checksumURL string) ([]byte, error) {
	fetch := func(url string, limit int64) ([]byte, error) {
		request, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
		if err != nil {
			return nil, err
		}
		response, err := client.Do(request)
		if err != nil {
			return nil, err
		}
		defer response.Body.Close()
		if response.StatusCode != http.StatusOK {
			return nil, errors.New("geosite download returned a non-success status")
		}
		body, err := io.ReadAll(io.LimitReader(response.Body, limit+1))
		if err != nil {
			return nil, err
		}
		if len(body) == 0 || int64(len(body)) > limit {
			return nil, errors.New("geosite download size is invalid")
		}
		return body, nil
	}
	checksum, err := fetch(checksumURL, 1024)
	if err != nil {
		return nil, err
	}
	fields := strings.Fields(string(checksum))
	if len(fields) == 0 {
		return nil, errors.New("missing geosite checksum")
	}
	expected, err := hex.DecodeString(fields[0])
	if err != nil || len(expected) != sha256.Size {
		return nil, errors.New("invalid geosite checksum")
	}
	body, err := fetch(dataURL, 32<<20)
	if err != nil {
		return nil, err
	}
	actual := sha256.Sum256(body)
	if string(actual[:]) != string(expected) {
		return nil, errors.New("geosite checksum mismatch")
	}
	return body, nil
}

var geositeReleaseTag = regexp.MustCompile(`^[0-9]{14}$`)

func latestGeosite(ctx context.Context, client *http.Client) ([]byte, string, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, "https://api.github.com/repos/v2fly/domain-list-community/releases/latest", nil)
	if err != nil {
		return nil, "", err
	}
	response, err := client.Do(req)
	if err != nil {
		return nil, "", err
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return nil, "", errors.New("cannot retrieve geosite release")
	}
	var release struct {
		Tag string `json:"tag_name"`
	}
	if err = json.NewDecoder(io.LimitReader(response.Body, 1<<20)).Decode(&release); err != nil {
		return nil, "", err
	}
	if !geositeReleaseTag.MatchString(release.Tag) {
		return nil, "", errors.New("invalid geosite release tag")
	}
	base := fmt.Sprintf("https://github.com/v2fly/domain-list-community/releases/download/%s/", release.Tag)
	body, err := downloadGeosite(ctx, client, base+"dlc.dat", base+"dlc.dat.sha256sum")
	return body, release.Tag, err
}

func writeGeositeFile(path string, body []byte) error {
	file, err := os.CreateTemp(filepath.Dir(path), ".geosite-*")
	if err != nil {
		return err
	}
	defer os.Remove(file.Name())
	if err = file.Chmod(0600); err == nil {
		_, err = file.Write(body)
	}
	if err == nil {
		err = file.Sync()
	}
	if closeErr := file.Close(); err == nil {
		err = closeErr
	}
	if err != nil {
		return err
	}
	if err = os.Rename(file.Name(), path); err != nil {
		return err
	}
	dir, err := os.Open(filepath.Dir(path))
	if err != nil {
		return err
	}
	defer dir.Close()
	return dir.Sync()
}

func recoverGeosite(dir string) error {
	path := filepath.Join(dir, "geosite.dat")
	backup := path + ".rollback"
	body, err := os.ReadFile(backup)
	if os.IsNotExist(err) {
		return nil
	}
	if err != nil {
		return err
	}
	if err = writeGeositeFile(path, body); err != nil {
		return err
	}
	return os.Remove(backup)
}

func switchGeosite(dir string, body []byte, restart func(bool) error) error {
	path := filepath.Join(dir, "geosite.dat")
	old, err := os.ReadFile(path)
	if err != nil {
		return err
	}
	if string(old) == string(body) {
		return nil
	}
	backup := path + ".rollback"
	if _, err = os.Stat(backup); err == nil {
		return errors.New("pending geosite recovery")
	} else if !os.IsNotExist(err) {
		return err
	}
	if err = writeGeositeFile(backup, old); err != nil {
		return err
	}
	if err = writeGeositeFile(path, body); err != nil {
		if rollbackErr := recoverGeosite(dir); rollbackErr != nil {
			return fmt.Errorf("write failed: %v; recovery failed: %w", err, rollbackErr)
		}
		return err
	}
	if err = restart(false); err != nil {
		if rollbackErr := recoverGeosite(dir); rollbackErr != nil {
			return fmt.Errorf("geosite restart failed: %v; recovery failed: %w", err, rollbackErr)
		}
		if rollbackErr := restart(true); rollbackErr != nil {
			return fmt.Errorf("geosite restart failed: %v; old core restart failed: %w", err, rollbackErr)
		}
		return fmt.Errorf("geosite restart failed and data restored: %w", err)
	}
	if err = writeGeositeFile(path+".previous", old); err != nil {
		return err
	}
	return os.Remove(backup)
}

func (c *Core) validateGeosite(ctx context.Context, body []byte) error {
	dir := c.config.GeositeAssetDir
	stage, err := os.MkdirTemp(dir, ".geosite-candidate-*")
	if err != nil {
		return err
	}
	defer os.RemoveAll(stage)
	entries, err := os.ReadDir(dir)
	if err != nil {
		return err
	}
	for _, entry := range entries {
		if entry.IsDir() || entry.Name() == "geosite.dat" || entry.Name() == "geosite.dat.rollback" {
			continue
		}
		if err = os.Symlink(filepath.Join(dir, entry.Name()), filepath.Join(stage, entry.Name())); err != nil {
			return err
		}
	}
	if err = os.WriteFile(filepath.Join(stage, "geosite.dat"), body, 0600); err != nil {
		return err
	}
	command := exec.CommandContext(ctx, c.config.CoreBinary, "test", "-format=json", "-c", c.config.CoreConfigPath)
	command.Env = c.assetEnv(stage)
	command.Stdout, command.Stderr = os.Stdout, os.Stderr
	if err = command.Run(); err != nil {
		return errors.New("core rejected candidate geosite")
	}
	return nil
}
