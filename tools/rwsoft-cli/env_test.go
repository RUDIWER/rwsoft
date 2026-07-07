package main

import (
	"strings"
	"testing"
)

func TestResolveEnvConfigUsesProfileDefaults(t *testing.T) {
	profile, err := resolveProfile("lerd")
	if err != nil {
		t.Fatalf("resolve profile: %v", err)
	}

	config, err := ResolveEnvConfig(profile, EnvConfig{}, true)
	if err != nil {
		t.Fatalf("resolve env config: %v", err)
	}

	if config.AppURL != "http://rwsoft.test" {
		t.Fatalf("unexpected app url: %s", config.AppURL)
	}

	if config.DBConnection != "mysql" || config.DBDatabase != "rwsoft" {
		t.Fatalf("unexpected database config: %#v", config)
	}

	if config.TenantStorage != "create_database" {
		t.Fatalf("unexpected tenant storage: %s", config.TenantStorage)
	}
}

func TestResolveEnvConfigSetsSharedDatabaseDefault(t *testing.T) {
	profile, err := resolveProfile("laravel-cloud")
	if err != nil {
		t.Fatalf("resolve profile: %v", err)
	}

	config, err := ResolveEnvConfig(profile, EnvConfig{DBDatabase: "central_db"}, true)
	if err != nil {
		t.Fatalf("resolve env config: %v", err)
	}

	if config.TenantStorage != "shared_prefixed" {
		t.Fatalf("unexpected tenant storage: %s", config.TenantStorage)
	}

	if config.SharedDatabase != "central_db" {
		t.Fatalf("unexpected shared database: %s", config.SharedDatabase)
	}
}

func TestApplyEnvDefaultsRewritesExistingValuesAndAppendsMissingValues(t *testing.T) {
	contents := "APP_URL=http://localhost\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n"
	updated := applyEnvDefaults(contents, map[string]string{
		"APP_URL":       "http://rwsoft.test",
		"DB_CONNECTION": "mysql",
		"DB_HOST":       "127.0.0.1",
	})

	for _, expected := range []string{
		"APP_URL=http://rwsoft.test",
		"DB_CONNECTION=mysql",
		"DB_HOST=127.0.0.1",
	} {
		if !strings.Contains(updated, expected) {
			t.Fatalf("expected %q in updated env:\n%s", expected, updated)
		}
	}
}
