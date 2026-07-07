package main

import (
	"fmt"
	"os"
	"runtime"
)

type Profile struct {
	Name              string
	DefaultAppURL     string
	DefaultDB         string
	DefaultDBHost     string
	DefaultDBPort     string
	DefaultDBName     string
	DefaultDBUser     string
	DefaultDBPassword string
	TenantStorage     string
	RequiresDocker    bool
	RequiresComposer  bool
	RequiresPHP       bool
	RequiresGit       bool
	RequiresNode      bool
}

func resolveProfile(name string) (Profile, error) {
	if name == "" || name == "auto" {
		name = detectProfile()
	}

	switch name {
	case "lerd":
		return Profile{Name: "lerd", DefaultAppURL: "http://rwsoft.test", DefaultDB: "mysql", DefaultDBHost: "lerd-mysql", DefaultDBPort: "3306", DefaultDBName: "rwsoft", DefaultDBUser: "root", DefaultDBPassword: "lerd", TenantStorage: "create_database", RequiresComposer: true, RequiresPHP: true, RequiresGit: true, RequiresNode: true}, nil
	case "herd":
		return Profile{Name: "herd", DefaultAppURL: "http://rwsoft.test", DefaultDB: "mysql", DefaultDBHost: "127.0.0.1", DefaultDBPort: "3306", DefaultDBName: "rwsoft", DefaultDBUser: "root", TenantStorage: "create_database", RequiresComposer: true, RequiresPHP: true, RequiresGit: true, RequiresNode: true}, nil
	case "docker":
		return Profile{Name: "docker", DefaultAppURL: "http://localhost", DefaultDB: "mysql", DefaultDBHost: "mysql", DefaultDBPort: "3306", DefaultDBName: "rwsoft", DefaultDBUser: "root", DefaultDBPassword: "rwsoft", TenantStorage: "create_database", RequiresDocker: true, RequiresGit: true, RequiresNode: false}, nil
	case "laravel-cloud":
		return Profile{Name: "laravel-cloud", DefaultAppURL: "https://example.com", DefaultDB: "mysql", DefaultDBHost: "127.0.0.1", DefaultDBPort: "3306", DefaultDBName: "rwsoft", DefaultDBUser: "root", TenantStorage: "shared_prefixed", RequiresComposer: true, RequiresPHP: true, RequiresGit: true, RequiresNode: false}, nil
	default:
		return Profile{}, fmt.Errorf("unsupported profile %q", name)
	}
}

func detectProfile() string {
	if _, ok := os.LookupEnv("LARAVEL_CLOUD"); ok {
		return "laravel-cloud"
	}

	if commandExists("lerd") {
		return "lerd"
	}

	if runtime.GOOS == "darwin" && looksLikeHerd() {
		return "herd"
	}

	if commandExists("docker") {
		return "docker"
	}

	if runtime.GOOS == "darwin" {
		return "herd"
	}

	return "lerd"
}

func looksLikeHerd() bool {
	home, err := os.UserHomeDir()
	if err != nil {
		return false
	}

	paths := []string{
		home + "/Library/Application Support/Herd",
		"/Applications/Herd.app",
	}

	for _, path := range paths {
		if _, err := os.Stat(path); err == nil {
			return true
		}
	}

	return false
}
