package main

import (
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"strings"
	"time"
)

var version = "dev"

const defaultRepositoryURL = "https://github.com/RUDIWER/rwsoft.git"

func main() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Minute)
	defer cancel()

	if err := run(ctx, os.Args[1:]); err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		os.Exit(1)
	}
}

func run(ctx context.Context, args []string) error {
	if len(args) == 0 {
		printUsage()
		return nil
	}

	switch args[0] {
	case "version", "--version", "-v":
		fmt.Printf("rwsoft %s (%s/%s)\n", version, runtime.GOOS, runtime.GOARCH)
		return nil
	case "doctor":
		return runDoctor(args[1:])
	case "install":
		return runInstall(ctx, args[1:])
	case "help", "--help", "-h":
		printUsage()
		return nil
	default:
		return fmt.Errorf("unknown command %q", args[0])
	}
}

func printUsage() {
	fmt.Println(`RwSoft installer

Usage:
  rwsoft version
  rwsoft doctor [--profile=auto|lerd|herd|docker|laravel-cloud]
  rwsoft install [target] [options]

Install options:
  --profile=auto|lerd|herd|docker|laravel-cloud
  --repo=https://github.com/RUDIWER/rwsoft.git
  --branch=main|latest|vX.Y.Z
  --source=/local/path
  --dry-run
  --force
  --skip-composer
  --skip-npm
  --skip-artisan
  --skip-dependency-install
  --no-interaction
  --app-url=http://rwsoft.test
  --db-connection=mysql
  --db-host=127.0.0.1
  --db-port=3306
  --db-database=rwsoft
  --db-username=root
  --db-password=secret
  --tenant-storage=create_database|existing_database|shared_prefixed
  --shared-database=rwsoft
  --platform-admin-email=admin@example.com
  --skip-site
  --site-name="RwSoft"
  --site-slug=rwsoft
  --site-domain=rwsoft.test
  --site-admin-email=admin@example.com
  --site-tenant-database=rwsoft_site
  --site-tenant-prefix=t_rwsoft_

The Go toolchain is not required for normal installation. Release bootstrap
scripts download a prebuilt rwsoft binary for the current OS and CPU.`)
}

func runDoctor(args []string) error {
	flags := flag.NewFlagSet("doctor", flag.ContinueOnError)
	profileName := flags.String("profile", "auto", "installation profile")
	if err := flags.Parse(args); err != nil {
		return err
	}

	profile, err := resolveProfile(*profileName)
	if err != nil {
		return err
	}

	report := inspectSystem(profile)
	report.Print()

	if report.HasRequiredFailures() {
		return errors.New("required checks failed")
	}

	return nil
}

func runInstall(ctx context.Context, args []string) error {
	target, args := splitInstallTarget(args)

	flags := flag.NewFlagSet("install", flag.ContinueOnError)
	profileName := flags.String("profile", "auto", "installation profile")
	repoURL := flags.String("repo", defaultRepositoryURL, "git repository URL")
	branch := flags.String("branch", "main", "git branch, tag, or latest release to clone")
	sourcePath := flags.String("source", "", "local source directory instead of git clone")
	dryRun := flags.Bool("dry-run", false, "show actions without changing files")
	force := flags.Bool("force", false, "allow using a non-empty target directory")
	skipComposer := flags.Bool("skip-composer", false, "skip composer install")
	skipNPM := flags.Bool("skip-npm", false, "skip npm install")
	skipArtisan := flags.Bool("skip-artisan", false, "skip php artisan rwsoft:install")
	skipDependencyInstall := flags.Bool("skip-dependency-install", false, "do not try to install missing system dependencies")
	noInteraction := flags.Bool("no-interaction", false, "pass --no-interaction where supported")
	appURL := flags.String("app-url", "", "APP_URL value")
	dbConnection := flags.String("db-connection", "", "DB_CONNECTION value")
	dbHost := flags.String("db-host", "", "DB_HOST value")
	dbPort := flags.String("db-port", "", "DB_PORT value")
	dbDatabase := flags.String("db-database", "", "DB_DATABASE value")
	dbUsername := flags.String("db-username", "", "DB_USERNAME value")
	dbPassword := flags.String("db-password", "", "DB_PASSWORD value")
	tenantStorage := flags.String("tenant-storage", "", "tenant storage mode: create_database, existing_database, shared_prefixed")
	sharedDatabase := flags.String("shared-database", "", "TENANCY_SHARED_DATABASE value")
	platformAdminEmail := flags.String("platform-admin-email", "", "existing central user email to promote as platform admin")
	siteName := flags.String("site-name", "", "first site name")
	siteSlug := flags.String("site-slug", "", "first site slug")
	siteDomain := flags.String("site-domain", "", "first site primary domain")
	siteAdminEmail := flags.String("site-admin-email", "", "existing central user email to attach to the first site")
	siteTenantDatabase := flags.String("site-tenant-database", "", "first site tenant database name")
	siteTenantPrefix := flags.String("site-tenant-prefix", "", "first site tenant table prefix for shared_prefixed mode")
	skipSite := flags.Bool("skip-site", false, "do not create the first site during the Artisan install phase")

	if err := flags.Parse(args); err != nil {
		return err
	}

	if flags.NArg() > 0 {
		target = flags.Arg(0)
	}

	absTarget, err := filepath.Abs(target)
	if err != nil {
		return err
	}

	profile, err := resolveProfile(*profileName)
	if err != nil {
		return err
	}

	branchName := strings.TrimSpace(*branch)
	if branchName == "" {
		branchName = "main"
	}

	source := strings.TrimSpace(*sourcePath)
	if source == "" {
		branchName, err = resolveInstallBranch(ctx, *repoURL, branchName, *dryRun)
		if err != nil {
			return err
		}
	}

	options := InstallOptions{
		Profile:               profile,
		TargetDir:             absTarget,
		RepositoryURL:         *repoURL,
		Branch:                branchName,
		SourcePath:            source,
		DryRun:                *dryRun,
		Force:                 *force,
		SkipComposer:          *skipComposer,
		SkipNPM:               *skipNPM,
		SkipArtisan:           *skipArtisan,
		SkipDependencyInstall: *skipDependencyInstall,
		NoInteraction:         *noInteraction,
		PlatformAdminEmail:    strings.TrimSpace(*platformAdminEmail),
		Env: EnvConfig{
			AppURL:         strings.TrimSpace(*appURL),
			DBConnection:   strings.TrimSpace(*dbConnection),
			DBHost:         strings.TrimSpace(*dbHost),
			DBPort:         strings.TrimSpace(*dbPort),
			DBDatabase:     strings.TrimSpace(*dbDatabase),
			DBUsername:     strings.TrimSpace(*dbUsername),
			DBPassword:     *dbPassword,
			TenantStorage:  strings.TrimSpace(*tenantStorage),
			SharedDatabase: strings.TrimSpace(*sharedDatabase),
		},
		Site: SiteConfig{
			Name:           strings.TrimSpace(*siteName),
			Slug:           strings.TrimSpace(*siteSlug),
			Domain:         strings.TrimSpace(*siteDomain),
			AdminEmail:     strings.TrimSpace(*siteAdminEmail),
			TenantDatabase: strings.TrimSpace(*siteTenantDatabase),
			TenantPrefix:   strings.TrimSpace(*siteTenantPrefix),
			Skip:           *skipSite,
		},
	}

	return Install(ctx, options)
}

func resolveInstallBranch(ctx context.Context, repoURL string, branch string, dryRun bool) (string, error) {
	if branch != "latest" {
		return branch, nil
	}

	repository, ok := githubRepositoryFromURL(repoURL)
	if !ok {
		return "", fmt.Errorf("--branch=latest is only supported for GitHub repositories")
	}

	tagName, err := latestGitHubReleaseTag(ctx, repository)
	if err != nil {
		return "", err
	}

	if dryRun {
		fmt.Printf("DRY-RUN: resolved latest release for %s to %s\n", repository, tagName)
	} else {
		fmt.Printf("Resolved latest release for %s to %s\n", repository, tagName)
	}

	return tagName, nil
}

func githubRepositoryFromURL(repoURL string) (string, bool) {
	repoURL = strings.TrimSpace(strings.TrimSuffix(repoURL, ".git"))
	patterns := []*regexp.Regexp{
		regexp.MustCompile(`^https://github\.com/([^/]+/[^/]+)$`),
		regexp.MustCompile(`^git@github\.com:([^/]+/[^/]+)$`),
	}

	for _, pattern := range patterns {
		matches := pattern.FindStringSubmatch(repoURL)
		if len(matches) == 2 {
			return matches[1], true
		}
	}

	return "", false
}

func latestGitHubReleaseTag(ctx context.Context, repository string) (string, error) {
	request, err := http.NewRequestWithContext(ctx, http.MethodGet, "https://api.github.com/repos/"+repository+"/releases/latest", nil)
	if err != nil {
		return "", err
	}
	request.Header.Set("Accept", "application/vnd.github+json")
	request.Header.Set("User-Agent", "rwsoft-installer")

	response, err := http.DefaultClient.Do(request)
	if err != nil {
		return "", err
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return "", fmt.Errorf("could not resolve latest GitHub release for %s: %s", repository, response.Status)
	}

	var payload struct {
		TagName string `json:"tag_name"`
	}
	if err := json.NewDecoder(response.Body).Decode(&payload); err != nil {
		return "", err
	}

	if strings.TrimSpace(payload.TagName) == "" {
		return "", fmt.Errorf("latest GitHub release for %s has no tag_name", repository)
	}

	return payload.TagName, nil
}

func splitInstallTarget(args []string) (string, []string) {
	if len(args) > 0 && !strings.HasPrefix(args[0], "-") {
		return args[0], args[1:]
	}

	return "rwsoft", args
}
