package main

import (
	"context"
	"flag"
	"log"
	"os"
	"os/signal"
	"syscall"

	"github.com/yap/yap-agent/internal/agent"
)

func main() {
	path := flag.String("config", "/etc/yap-agent/config.json", "local mode-0600 configuration file")
	flag.Parse()
	if e := run(*path); e != nil {
		log.Print(e)
		os.Exit(1)
	}
}
func run(path string) error {
	config, e := agent.LoadConfig(path)
	if e != nil {
		return e
	}
	store, e := agent.OpenStore(config.StatePath)
	if e != nil {
		return e
	}
	defer store.Close()
	ctx, cancel := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer cancel()
	return agent.NewRuntime(store, agent.NewPanel(config), agent.NewCore(config)).Run(ctx)
}
