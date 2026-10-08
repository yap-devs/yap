package agent

import (
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"syscall"

	_ "github.com/mattn/go-sqlite3"
)

type Store struct {
	db   *sql.DB
	lock *os.File
}

func OpenStore(path string) (*Store, error) {
	if err := os.MkdirAll(filepath.Dir(path), 0700); err != nil {
		return nil, err
	}
	f, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0600)
	if err != nil {
		return nil, err
	}
	f.Close()
	if err = os.Chmod(path, 0600); err != nil {
		return nil, err
	}
	lock, err := os.OpenFile(path+".lock", os.O_CREATE|os.O_RDWR, 0600)
	if err != nil {
		return nil, err
	}
	if err = syscall.Flock(int(lock.Fd()), syscall.LOCK_EX|syscall.LOCK_NB); err != nil {
		lock.Close()
		return nil, errors.New("another agent owns this state database")
	}
	db, err := sql.Open("sqlite3", path+"?_journal_mode=WAL&_synchronous=FULL&_busy_timeout=5000")
	if err != nil {
		lock.Close()
		return nil, err
	}
	db.SetMaxOpenConns(1)
	_, err = db.Exec(`CREATE TABLE IF NOT EXISTS state (key TEXT PRIMARY KEY,value BLOB NOT NULL);
 CREATE TABLE IF NOT EXISTS baseline (user_id INTEGER,route_id INTEGER,uplink INTEGER NOT NULL,downlink INTEGER NOT NULL,PRIMARY KEY(user_id,route_id));
 CREATE TABLE IF NOT EXISTS aggregate (user_id INTEGER,route_id INTEGER,uplink INTEGER NOT NULL,downlink INTEGER NOT NULL,PRIMARY KEY(user_id,route_id));`)
	if err != nil {
		db.Close()
		lock.Close()
		return nil, err
	}
	return &Store{db: db, lock: lock}, nil
}
func (s *Store) Close() error { err := s.db.Close(); s.lock.Close(); return err }
func (s *Store) SaveDesired(d Desired) error {
	b, e := json.Marshal(d)
	if e != nil {
		return e
	}
	_, e = s.db.Exec(`INSERT INTO state VALUES('desired',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value`, b)
	return e
}
func (s *Store) Desired() (Desired, error) {
	var d Desired
	var b []byte
	e := s.db.QueryRow(`SELECT value FROM state WHERE key='desired'`).Scan(&b)
	if errors.Is(e, sql.ErrNoRows) {
		return d, nil
	}
	if e != nil {
		return d, e
	}
	e = json.Unmarshal(b, &d)
	return d, e
}

func (s *Store) Sample(generation string, counters []Counter) error {
	tx, e := s.db.Begin()
	if e != nil {
		return e
	}
	defer tx.Rollback()
	var old string
	e = tx.QueryRow(`SELECT value FROM state WHERE key='generation'`).Scan(&old)
	if e != nil && !errors.Is(e, sql.ErrNoRows) {
		return e
	}
	if old != generation {
		if _, e = tx.Exec(`DELETE FROM baseline`); e != nil {
			return e
		}
		if _, e = tx.Exec(`INSERT INTO state VALUES('generation',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value`, generation); e != nil {
			return e
		}
	}
	for _, c := range counters {
		if c.Uplink < 0 || c.Downlink < 0 {
			return errors.New("negative core counter")
		}
		var up, down int64
		e = tx.QueryRow(`SELECT uplink,downlink FROM baseline WHERE user_id=? AND route_id=?`, c.UserID, c.RouteID).Scan(&up, &down)
		if e != nil && !errors.Is(e, sql.ErrNoRows) {
			return e
		}
		if c.Uplink < up || c.Downlink < down {
			return errors.New("counter decreased within one core generation")
		}
		if _, e = tx.Exec(`INSERT INTO aggregate VALUES(?,?,?,?) ON CONFLICT(user_id,route_id) DO UPDATE SET uplink=uplink+excluded.uplink,downlink=downlink+excluded.downlink`, c.UserID, c.RouteID, c.Uplink-up, c.Downlink-down); e != nil {
			return e
		}
		if _, e = tx.Exec(`INSERT INTO baseline VALUES(?,?,?,?) ON CONFLICT(user_id,route_id) DO UPDATE SET uplink=excluded.uplink,downlink=excluded.downlink`, c.UserID, c.RouteID, c.Uplink, c.Downlink); e != nil {
			return e
		}
	}
	return tx.Commit()
}

func (s *Store) Pending() ([]byte, error) {
	tx, e := s.db.Begin()
	if e != nil {
		return nil, e
	}
	defer tx.Rollback()
	var body []byte
	e = tx.QueryRow(`SELECT value FROM state WHERE key='outbox'`).Scan(&body)
	if e == nil {
		return body, nil
	}
	if !errors.Is(e, sql.ErrNoRows) {
		return nil, e
	}
	var cursor [2]int64
	var cursorBody []byte
	e = tx.QueryRow(`SELECT value FROM state WHERE key='cursor'`).Scan(&cursorBody)
	if e != nil && !errors.Is(e, sql.ErrNoRows) {
		return nil, e
	}
	if len(cursorBody) > 0 {
		if e = json.Unmarshal(cursorBody, &cursor); e != nil {
			return nil, e
		}
	}
	rows, e := tx.Query(`SELECT user_id,route_id,uplink,downlink FROM aggregate WHERE uplink>0 OR downlink>0 ORDER BY (user_id>? OR (user_id=? AND route_id>?)) DESC,user_id,route_id LIMIT 1000`, cursor[0], cursor[0], cursor[1])
	if e != nil {
		return nil, e
	}
	batch := Batch{UUID: NewUUID()}
	for rows.Next() {
		var r Record
		if e = rows.Scan(&r.UserID, &r.RouteID, &r.Uplink, &r.Downlink); e != nil {
			rows.Close()
			return nil, e
		}
		batch.Records = append(batch.Records, r)
	}
	e = rows.Err()
	rows.Close()
	if e != nil {
		return nil, e
	}
	if len(batch.Records) == 0 {
		return nil, nil
	}
	body, e = json.Marshal(batch)
	if e != nil {
		return nil, e
	}
	if _, e = tx.Exec(`INSERT INTO state VALUES('outbox',?)`, body); e != nil {
		return nil, e
	}
	if len(body) > 1<<20 {
		return nil, errors.New("traffic batch exceeds payload limit")
	}
	for _, r := range batch.Records {
		if _, e = tx.Exec(`DELETE FROM aggregate WHERE user_id=? AND route_id=?`, r.UserID, r.RouteID); e != nil {
			return nil, e
		}
	}
	last := batch.Records[len(batch.Records)-1]
	cursorBody, e = json.Marshal([2]int64{last.UserID, last.RouteID})
	if e != nil {
		return nil, e
	}
	if _, e = tx.Exec(`INSERT INTO state VALUES('cursor',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value`, cursorBody); e != nil {
		return nil, e
	}
	return body, tx.Commit()
}
func (s *Store) Ack(uuid string) error {
	tx, e := s.db.Begin()
	if e != nil {
		return e
	}
	defer tx.Rollback()
	var body []byte
	if e = tx.QueryRow(`SELECT value FROM state WHERE key='outbox'`).Scan(&body); e != nil {
		return e
	}
	var b Batch
	if e = json.Unmarshal(body, &b); e != nil {
		return e
	}
	if uuid != b.UUID {
		return errors.New("acknowledgement UUID does not match pending batch")
	}
	if _, e = tx.Exec(`DELETE FROM state WHERE key='outbox'`); e != nil {
		return e
	}
	return tx.Commit()
}
func NewUUID() string {
	var b [16]byte
	if _, e := rand.Read(b[:]); e != nil {
		panic(e)
	}
	b[6] = (b[6] & 15) | 64
	b[8] = (b[8] & 63) | 128
	s := hex.EncodeToString(b[:])
	return s[:8] + "-" + s[8:12] + "-" + s[12:16] + "-" + s[16:20] + "-" + s[20:]
}
