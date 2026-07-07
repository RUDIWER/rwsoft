package main

import "testing"

func TestSiteConfigArtisanArgs(t *testing.T) {
	config := SiteConfig{
		Name:           "Demo Site",
		Slug:           "demo-site",
		Domain:         "demo.test",
		AdminEmail:     "admin@example.com",
		TenantDatabase: "demo_tenant",
		TenantPrefix:   "t_demo_",
	}

	args := config.ArtisanArgs()
	expected := []string{
		"--site-name=Demo Site",
		"--site-slug=demo-site",
		"--site-domain=demo.test",
		"--site-admin-email=admin@example.com",
		"--site-tenant-database=demo_tenant",
		"--site-tenant-prefix=t_demo_",
	}

	if len(args) != len(expected) {
		t.Fatalf("unexpected args: %#v", args)
	}

	for index, value := range expected {
		if args[index] != value {
			t.Fatalf("arg %d = %q, want %q", index, args[index], value)
		}
	}
}

func TestSiteConfigArtisanArgsCanSkipSite(t *testing.T) {
	args := (SiteConfig{Skip: true}).ArtisanArgs()

	if len(args) != 1 || args[0] != "--skip-site" {
		t.Fatalf("unexpected args: %#v", args)
	}
}

func TestSplitInstallTargetAllowsTargetBeforeFlags(t *testing.T) {
	target, args := splitInstallTarget([]string{"/tmp/rwsoft", "--dry-run", "--profile=lerd"})

	if target != "/tmp/rwsoft" {
		t.Fatalf("target = %q", target)
	}

	if len(args) != 2 || args[0] != "--dry-run" || args[1] != "--profile=lerd" {
		t.Fatalf("args = %#v", args)
	}
}

func TestArtisanInstallArgsIncludeTenantStorage(t *testing.T) {
	profile := Profile{Name: "docker"}
	site := SiteConfig{Name: "Demo"}
	env := EnvConfig{TenantStorage: "shared_prefixed"}

	args := ArtisanInstallArgs(profile, "admin@example.com", site, env)
	expected := []string{"rwsoft:install", "--profile=docker", "--tenant-storage=shared_prefixed", "--platform-admin-email=admin@example.com", "--site-name=Demo"}

	if len(args) != len(expected) {
		t.Fatalf("args = %#v", args)
	}

	for index, value := range expected {
		if args[index] != value {
			t.Fatalf("arg %d = %q, want %q", index, args[index], value)
		}
	}
}

func TestShouldSkipSourceCopyExcludesSecretsAndRuntimeArtifacts(t *testing.T) {
	skipped := []string{
		".env",
		".env.production",
		".agents/skills/laravel-security-audit",
		"AGENTS.md",
		"vendor",
		"vendor/autoload.php",
		"node_modules",
		"storage/logs/laravel.log",
		"bootstrap/cache/packages.php",
		"tools/rwsoft-cli/dist/rwsoft-linux-amd64",
	}

	for _, path := range skipped {
		if !shouldSkipSourceCopy(path) {
			t.Fatalf("expected %s to be skipped", path)
		}
	}

	if shouldSkipSourceCopy(".env.example") {
		t.Fatal(".env.example must be copied")
	}

	if shouldSkipSourceCopy("bootstrap/cache/.gitignore") {
		t.Fatal("bootstrap/cache/.gitignore must be copied so Laravel has a writable cache directory")
	}
}
