package main

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"strings"
)

type Runner struct {
	DryRun bool
	Dir    string
}

func commandExists(name string) bool {
	_, ok := lookupCommand(name)
	return ok
}

func lookupCommand(name string) (string, bool) {
	path, err := exec.LookPath(name)
	if err != nil {
		return "", false
	}

	return path, true
}

func (r Runner) Run(ctx context.Context, name string, args ...string) error {
	pretty := name
	if len(args) > 0 {
		pretty += " " + strings.Join(args, " ")
	}

	if r.DryRun {
		fmt.Printf("DRY-RUN: %s\n", pretty)
		return nil
	}

	fmt.Printf("RUN: %s\n", pretty)
	cmd := exec.CommandContext(ctx, name, args...)
	cmd.Dir = r.Dir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	cmd.Stdin = os.Stdin

	return cmd.Run()
}
