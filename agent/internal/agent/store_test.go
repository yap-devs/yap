package agent

import (
	"bytes"
	"encoding/json"
	"path/filepath"
	"testing"
)

func TestDurableImmutableBatchAndGeneration(t *testing.T) {
	path := filepath.Join(t.TempDir(), "state.sqlite")
	s, e := OpenStore(path)
	if e != nil {
		t.Fatal(e)
	}
	if e = s.Sample("generation-one", []Counter{{1, 10, 100, 200}}); e != nil {
		t.Fatal(e)
	}
	first, e := s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	var batch Batch
	if e = json.Unmarshal(first, &batch); e != nil {
		t.Fatal(e)
	}
	if e = s.Sample("generation-one", []Counter{{1, 10, 150, 250}}); e != nil {
		t.Fatal(e)
	}
	s.Close()
	s, e = OpenStore(path)
	if e != nil {
		t.Fatal(e)
	}
	defer s.Close()
	retry, e := s.Pending()
	if e != nil || !bytes.Equal(first, retry) {
		t.Fatalf("batch changed across restart: %s %s %v", first, retry, e)
	}
	if e = s.Ack("wrong-uuid"); e == nil {
		t.Fatal("mismatched ACK accepted")
	}
	if e = s.Ack(batch.UUID); e != nil {
		t.Fatal(e)
	}
	second, e := s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	json.Unmarshal(second, &batch)
	if len(batch.Records) != 1 || batch.Records[0].Uplink != 50 || batch.Records[0].Downlink != 50 {
		t.Fatalf("bad delta: %+v", batch)
	}
	s.Ack(batch.UUID)
	if e = s.Sample("generation-one", []Counter{{1, 10, 1, 2}}); e == nil {
		t.Fatal("same-generation reset accepted")
	}
	if e = s.Sample("generation-two", []Counter{{1, 10, 7, 8}}); e != nil {
		t.Fatal(e)
	}
	third, e := s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	json.Unmarshal(third, &batch)
	if batch.Records[0].Uplink != 7 || batch.Records[0].Downlink != 8 {
		t.Fatalf("bad new-generation delta: %+v", batch)
	}
}
func TestSampleRollback(t *testing.T) {
	s, e := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer s.Close()
	if e = s.Sample("g", []Counter{{1, 1, 10, 20}, {2, 1, -1, 2}}); e == nil {
		t.Fatal("invalid sample accepted")
	}
	b, e := s.Pending()
	if e != nil || b != nil {
		t.Fatal("partial sample persisted")
	}
}

func TestBatchRecordLimitPreservesRemainder(t *testing.T) {
	s, e := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer s.Close()
	counters := make([]Counter, 1001)
	for i := range counters {
		counters[i] = Counter{UserID: int64(i + 1), RouteID: 1, Uplink: 1, Downlink: 2}
	}
	if e = s.Sample("g", counters); e != nil {
		t.Fatal(e)
	}
	body, e := s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	var b Batch
	json.Unmarshal(body, &b)
	if len(b.Records) != 1000 || len(body) > 1<<20 {
		t.Fatal("invalid batch bounds")
	}
	if e = s.Ack(b.UUID); e != nil {
		t.Fatal(e)
	}
	body, e = s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	json.Unmarshal(body, &b)
	if len(b.Records) != 1 || b.Records[0].UserID != 1001 {
		t.Fatalf("lost remainder: %+v", b)
	}
}

func TestBatchCursorAvoidsStarvingLaterActiveUsers(t *testing.T) {
	s, e := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer s.Close()
	counters := make([]Counter, 1001)
	for i := range counters {
		counters[i] = Counter{UserID: int64(i + 1), RouteID: 1, Uplink: 1}
	}
	if e = s.Sample("g", counters); e != nil {
		t.Fatal(e)
	}
	body, e := s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	var batch Batch
	json.Unmarshal(body, &batch)
	if e = s.Ack(batch.UUID); e != nil {
		t.Fatal(e)
	}
	for i := range counters {
		counters[i].Uplink++
	}
	if e = s.Sample("g", counters); e != nil {
		t.Fatal(e)
	}
	body, e = s.Pending()
	if e != nil {
		t.Fatal(e)
	}
	json.Unmarshal(body, &batch)
	if batch.Records[0].UserID != 1001 || batch.Records[0].Uplink != 2 {
		t.Fatalf("later active user starved: %+v", batch.Records[0])
	}
}
