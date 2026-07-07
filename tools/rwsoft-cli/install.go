package main

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
)

type InstallOptions struct {
	Profile               Profile
	TargetDir             string
	RepositoryURL         string
	Branch                string
	SourcePath            string
	DryRun                bool
	Force                 bool
	SkipComposer          bool
	SkipNPM               bool
	SkipArtisan           bool
	SkipDependencyInstall bool
	NoInteraction         bool
	PlatformAdminEmail    string
	Env                   EnvConfig
	Site                  SiteConfig
}

func Install(ctx context.Context, options InstallOptions) error {
	fmt.Printf("RwSoft install\nProfile: %s\nTarget: %s\n\n", options.Profile.Name, options.TargetDir)

	report := inspectSystem(options.Profile)
	report.Print()
	if report.HasRequiredFailures() {
		if options.SkipDependencyInstall {
			return fmt.Errorf("required checks failed; install missing dependencies and retry")
		}

		if err := InstallMissingDependencies(ctx, report, options.DryRun); err != nil {
			return err
		}

		if options.DryRun {
			fmt.Println("DRY-RUN: continue assuming dependencies will be installed")
		} else {
			report = inspectSystem(options.Profile)
			report.Print()

			if report.HasRequiredFailures() {
				return fmt.Errorf("required checks still fail after dependency installation attempt")
			}
		}
	}

	runner := Runner{DryRun: options.DryRun, Dir: options.TargetDir}

	if err := prepareProject(ctx, runner, options); err != nil {
		return err
	}

	envConfig, err := ResolveEnvConfig(options.Profile, options.Env, options.NoInteraction)
	if err != nil {
		return err
	}

	if err := EnsureEnvFile(options.TargetDir, options.Profile, envConfig, options.DryRun); err != nil {
		return err
	}

	if !options.SkipComposer && options.Profile.RequiresComposer {
		if err := runner.Run(ctx, "composer", "install"); err != nil {
			return err
		}
	}

	if !options.SkipNPM && options.Profile.RequiresNode {
		if err := runner.Run(ctx, "npm", "install"); err != nil {
			return err
		}
	}

	if options.Profile.RequiresDocker {
		return InstallWithDocker(ctx, runner, options, envConfig)
	}

	if !options.SkipArtisan && options.Profile.RequiresPHP {
		if err := runner.Run(ctx, "php", "artisan", "key:generate", "--force"); err != nil {
			return err
		}

		artisanArgs := append([]string{"artisan"}, ArtisanInstallArgs(options.Profile, options.PlatformAdminEmail, options.Site, envConfig)...)
		if options.NoInteraction {
			artisanArgs = append(artisanArgs, "--no-interaction")
		}

		if err := runner.Run(ctx, "php", artisanArgs...); err != nil {
			return err
		}
	}

	fmt.Println("\nRwSoft install completed.")
	return nil
}

func ArtisanInstallArgs(profile Profile, platformAdminEmail string, site SiteConfig, env EnvConfig) []string {
	args := []string{"rwsoft:install", "--profile=" + profile.Name}

	if env.TenantStorage != "" {
		args = append(args, "--tenant-storage="+env.TenantStorage)
	}

	if platformAdminEmail != "" {
		args = append(args, "--platform-admin-email="+platformAdminEmail)
	}

	args = append(args, site.ArtisanArgs()...)

	return args
}

func prepareProject(ctx context.Context, runner Runner, options InstallOptions) error {
	if isLaravelProject(options.TargetDir) {
		fmt.Println("Target already contains a Laravel project; using existing checkout")
		return nil
	}

	if err := ensureTargetIsUsable(options.TargetDir, options.Force, options.DryRun); err != nil {
		return err
	}

	if options.SourcePath != "" {
		return copySource(options.SourcePath, options.TargetDir, options.DryRun)
	}

	parent := filepath.Dir(options.TargetDir)
	if !options.DryRun {
		if err := os.MkdirAll(parent, 0755); err != nil {
			return err
		}
	}

	cloneRunner := Runner{DryRun: options.DryRun, Dir: parent}
	return cloneRunner.Run(ctx, "git", "clone", "--branch", options.Branch, options.RepositoryURL, options.TargetDir)
}

func ensureTargetIsUsable(path string, force bool, dryRun bool) error {
	entries, err := os.ReadDir(path)
	if os.IsNotExist(err) {
		if dryRun {
			fmt.Printf("DRY-RUN: create target directory %s\n", path)
			return nil
		}

		return os.MkdirAll(path, 0755)
	}

	if err != nil {
		return err
	}

	if len(entries) > 0 && !force {
		return fmt.Errorf("target directory is not empty: %s (use --force to continue)", path)
	}

	return nil
}

func isLaravelProject(path string) bool {
	if _, err := os.Stat(filepath.Join(path, "artisan")); err != nil {
		return false
	}

	if _, err := os.Stat(filepath.Join(path, "composer.json")); err != nil {
		return false
	}

	return true
}

func copySource(source string, target string, dryRun bool) error {
	absSource, err := filepath.Abs(source)
	if err != nil {
		return err
	}

	if dryRun {
		fmt.Printf("DRY-RUN: copy source %s to %s\n", absSource, target)
		return nil
	}

	return copyDir(absSource, target)
}
