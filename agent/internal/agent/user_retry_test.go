package agent

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"testing"
	"time"

	command "github.com/v2fly/v2ray-core/v5/app/proxyman/command"
	"github.com/v2fly/v2ray-core/v5/common/serial"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/status"
)

type retryHandlerClient struct {
	command.HandlerServiceClient
	err error
}

func (c retryHandlerClient) AlterInbound(context.Context, *command.AlterInboundRequest, ...grpc.CallOption) (*command.AlterInboundResponse, error) {
	return nil, c.err
}

func TestMissingUserRetryOnlyAcceptsTheExactPinnedHandlerError(t *testing.T) {
	for _, tc := range []struct {
		name string
		err  error
		ok   bool
	}{
		{"success", nil, true},
		{"missing target", status.Error(codes.Unknown, "proxy/vmess/inbound: User user-7 not found."), true},
		{"different identity", status.Error(codes.Unknown, "proxy/vmess/inbound: User other-user-7 not found."), false},
		{"different status", status.Error(codes.Unavailable, "proxy/vmess/inbound: User user-7 not found."), false},
		{"other handler failure", status.Error(codes.Unknown, "failed to get handler: yap-main"), false},
	} {
		t.Run(tc.name, func(t *testing.T) {
			err := removeInboundUser(context.Background(), retryHandlerClient{err: tc.err}, "yap-main", "user-7")
			if tc.ok && err != nil {
				t.Fatalf("idempotent removal failed: %v", err)
			}
			if !tc.ok && (err == nil || !errors.Is(err, tc.err)) {
				t.Fatal("unrelated handler failure was swallowed or replaced")
			}
		})
	}
}

func TestUserSynchronizationRetriesLostSuccessfulResponses(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	for _, scenario := range []string{"remove", "add", "add then revoke", "remove then restore", "partial additions then revoke"} {
		t.Run(scenario, func(t *testing.T) {
			ctx, cancel := context.WithTimeout(context.Background(), 45*time.Second)
			defer cancel()
			address := testCoreSocketPath(t)
			_, portText, _ := net.SplitHostPort(freeAddress(t))
			port, _ := strconv.Atoi(portText)
			desired := fixtureDesired()
			desired.Routes = desired.Routes[:1]
			desired.Routes[0].ListenPort = port
			desired.Users = desired.Users[:1]
			next := cloneDesired(desired)
			next.Revision++
			if scenario == "remove" || scenario == "remove then restore" {
				next.Users[0].UUID = "c2cda8b8-6c14-4c08-aef0-560f4102a821"
			} else {
				desired.Users = nil
			}
			if scenario == "partial additions then revoke" {
				next.Users = cloneDesired(fixtureDesired()).Users
				next.Users[1].LowPriority = false
			}
			core := NewCore(Config{CoreBinary: binary, CoreSocketPath: address, CoreConfigPath: filepath.Join(t.TempDir(), "core.json")})
			defer core.Stop()
			if err := core.Apply(ctx, desired); err != nil {
				t.Fatal(err)
			}
			generation := core.Generation()
			core.BeforeRestart = func(context.Context) error { return fmt.Errorf("statistics unavailable") }
			core.connection.Close()
			lost := false
			additions := 0
			var err error
			core.connection, err = grpc.NewClient("unix://"+address, grpc.WithTransportCredentials(insecure.NewCredentials()), grpc.WithUnaryInterceptor(func(ctx context.Context, method string, request, response any, connection *grpc.ClientConn, invoker grpc.UnaryInvoker, options ...grpc.CallOption) error {
				err := invoker(ctx, method, request, response, connection, options...)
				if err == nil && !lost && method == "/v2ray.core.app.proxyman.command.HandlerService/AlterInbound" {
					operation, decodeErr := serial.GetInstanceOf(request.(*command.AlterInboundRequest).Operation)
					if decodeErr != nil {
						return decodeErr
					}
					_, remove := operation.(*command.RemoveUserOperation)
					_, add := operation.(*command.AddUserOperation)
					if add {
						additions++
					}
					lose := ((scenario == "remove" || scenario == "remove then restore") && remove) || ((scenario == "add" || scenario == "add then revoke") && add) || (scenario == "partial additions then revoke" && add && additions == 2)
					if lose {
						lost = true
						return status.Error(codes.Unavailable, "successful response lost")
					}
				}
				return err
			}))
			if err != nil {
				t.Fatal(err)
			}
			if err = core.Apply(ctx, next); err == nil || !lost {
				t.Fatal("successful handler response was not lost")
			}
			// A changed target after the lost add must replace the actual identity.
			if scenario == "add" {
				next.Users[0].UUID = "c2cda8b8-6c14-4c08-aef0-560f4102a822"
			} else if scenario == "remove then restore" {
				next.Users = cloneDesired(desired).Users
			} else if scenario == "add then revoke" || scenario == "partial additions then revoke" {
				next.Users = nil
			}
			if err = core.Apply(ctx, next); err != nil {
				t.Fatalf("retry did not converge: %v", err)
			}
			if core.Generation() != generation {
				t.Fatal("handler retry restarted the core")
			}
			origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { fmt.Fprint(w, "retry succeeded") }))
			defer origin.Close()
			fetch := func(uuid string) error {
				response, err := networkVMessClient(t, binary, port, uuid).Get(origin.URL)
				if err != nil {
					return err
				}
				defer response.Body.Close()
				_, err = io.Copy(io.Discard, response.Body)
				return err
			}
			if len(next.Users) > 0 {
				if err = fetch(next.Users[0].UUID); err != nil {
					t.Fatalf("desired UUID is not authorized: %v", err)
				}
			}
			if scenario != "remove then restore" {
				if err = fetch(fixtureDesired().Users[0].UUID); err == nil {
					t.Fatal("old or partially added UUID remains authorized")
				}
			}
			if scenario == "partial additions then revoke" {
				if err = fetch(fixtureDesired().Users[1].UUID); err == nil {
					t.Fatal("partially added batch user remains authorized")
				}
			}
		})
	}
}

func TestUserSynchronizationSwapsUUIDsWithoutRemovingNewIdentities(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	d := fixtureDesired()
	d.Routes = d.Routes[:1]
	d.Users[1].LowPriority = false
	_, text, _ := net.SplitHostPort(freeAddress(t))
	d.Routes[0].ListenPort, _ = strconv.Atoi(text)
	core := NewCore(Config{CoreBinary: binary, CoreSocketPath: testCoreSocketPath(t), CoreConfigPath: filepath.Join(t.TempDir(), "core.json")})
	defer core.Stop()
	if err := core.Apply(ctx, d); err != nil {
		t.Fatal(err)
	}
	next := cloneDesired(d)
	next.Revision++
	next.Users[0].UUID, next.Users[1].UUID = next.Users[1].UUID, next.Users[0].UUID
	if err := core.Apply(ctx, next); err != nil {
		t.Fatal(err)
	}
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { fmt.Fprint(w, "ok") }))
	defer origin.Close()
	for _, u := range next.Users {
		response, err := networkVMessClient(t, binary, next.Routes[0].ListenPort, u.UUID).Get(origin.URL)
		if err != nil {
			t.Errorf("UUID swap accepted but user %d cannot authenticate: %v", u.ID, err)
			continue
		}
		_, err = io.Copy(io.Discard, response.Body)
		response.Body.Close()
		if err != nil {
			t.Error(err)
		}
	}
}
