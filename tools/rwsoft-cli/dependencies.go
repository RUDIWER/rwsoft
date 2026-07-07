package main

import (
	"context"
	"fmt"
	"runtime"
)

func InstallMissingDependencies(ctx context.Context, report DoctorReport, dryRun bool) error {
	missing := requiredMissingCommands(report)
	if len(missing) == 0 {
		return nil
	}

	fmt.Printf("\nInstalling missing dependencies: %v\n", missing)

	switch runtime.GOOS {
	case "linux":
		return installLinuxDependencies(ctx, missing, dryRun)
	case "darwin":
		return installMacDependencies(ctx, missing, dryRun)
	case "windows":
		return installWindowsDependencies(ctx, missing, dryRun)
	default:
		return fmt.Errorf("automatic dependency installation is not supported on %s", runtime.GOOS)
	}
}

func requiredMissingCommands(report DoctorReport) []string {
	var missing []string
	for _, check := range report.Checks {
		if check.Required && !check.OK {
			missing = append(missing, check.Name)
		}
	}

	return missing
}

func installLinuxDependencies(ctx context.Context, commands []string, dryRun bool) error {
	packages := linuxPackagesFor(commands)
	if len(packages) == 0 {
		return nil
	}

	runner := Runner{DryRun: dryRun}

	switch {
	case commandExists("apt-get"):
		if err := runner.Run(ctx, "sudo", "apt-get", "update"); err != nil {
			return err
		}

		args := append([]string{"apt-get", "install", "-y"}, packages...)
		return runner.Run(ctx, "sudo", args...)
	case commandExists("dnf"):
		args := append([]string{"dnf", "install", "-y"}, packages...)
		return runner.Run(ctx, "sudo", args...)
	case commandExists("yum"):
		args := append([]string{"yum", "install", "-y"}, packages...)
		return runner.Run(ctx, "sudo", args...)
	case commandExists("pacman"):
		args := append([]string{"pacman", "-S", "--needed", "--noconfirm"}, packages...)
		return runner.Run(ctx, "sudo", args...)
	default:
		return fmt.Errorf("no supported Linux package manager found; install %v manually", packages)
	}
}

func installMacDependencies(ctx context.Context, commands []string, dryRun bool) error {
	if !commandExists("brew") {
		return fmt.Errorf("Homebrew is required for automatic dependency installation on macOS")
	}

	packages := macPackagesFor(commands)
	if len(packages) == 0 {
		return nil
	}

	args := append([]string{"install"}, packages...)
	return Runner{DryRun: dryRun}.Run(ctx, "brew", args...)
}

func installWindowsDependencies(ctx context.Context, commands []string, dryRun bool) error {
	if !commandExists("winget") {
		return fmt.Errorf("winget is required for automatic dependency installation on Windows")
	}

	for _, id := range windowsPackagesFor(commands) {
		if err := (Runner{DryRun: dryRun}).Run(ctx, "winget", "install", "--id", id, "--exact", "--accept-source-agreements", "--accept-package-agreements"); err != nil {
			return err
		}
	}

	return nil
}

func linuxPackagesFor(commands []string) []string {
	seen := map[string]bool{}
	var packages []string
	for _, command := range commands {
		for _, pkg := range mapCommandToLinuxPackages(command) {
			if !seen[pkg] {
				seen[pkg] = true
				packages = append(packages, pkg)
			}
		}
	}

	return packages
}

func macPackagesFor(commands []string) []string {
	seen := map[string]bool{}
	var packages []string
	for _, command := range commands {
		pkg := mapCommandToMacPackage(command)
		if pkg != "" && !seen[pkg] {
			seen[pkg] = true
			packages = append(packages, pkg)
		}
	}

	return packages
}

func windowsPackagesFor(commands []string) []string {
	seen := map[string]bool{}
	var packages []string
	for _, command := range commands {
		pkg := mapCommandToWindowsPackage(command)
		if pkg != "" && !seen[pkg] {
			seen[pkg] = true
			packages = append(packages, pkg)
		}
	}

	return packages
}

func mapCommandToLinuxPackages(command string) []string {
	switch command {
	case "php":
		return []string{"php-cli", "php-mbstring", "php-xml", "php-curl", "php-zip", "php-mysql", "unzip"}
	case "composer":
		return []string{"composer"}
	case "git":
		return []string{"git"}
	case "node", "npm":
		return []string{"nodejs", "npm"}
	case "docker":
		return []string{"docker.io", "docker-compose-plugin"}
	case "php-version":
		return []string{"php-cli"}
	case "php-ext-ctype", "php-ext-fileinfo", "php-ext-openssl", "php-ext-pdo", "php-ext-tokenizer":
		return []string{"php-cli"}
	case "php-ext-curl":
		return []string{"php-curl"}
	case "php-ext-dom", "php-ext-xml":
		return []string{"php-xml"}
	case "php-ext-mbstring":
		return []string{"php-mbstring"}
	case "php-ext-pdo_mysql":
		return []string{"php-mysql"}
	case "php-ext-zip":
		return []string{"php-zip"}
	default:
		return nil
	}
}

func mapCommandToMacPackage(command string) string {
	switch command {
	case "php":
		return "php"
	case "composer":
		return "composer"
	case "git":
		return "git"
	case "node", "npm":
		return "node"
	case "docker":
		return "docker"
	case "php-version", "php-ext-ctype", "php-ext-curl", "php-ext-dom", "php-ext-fileinfo", "php-ext-mbstring", "php-ext-openssl", "php-ext-pdo", "php-ext-pdo_mysql", "php-ext-tokenizer", "php-ext-xml", "php-ext-zip":
		return "php"
	default:
		return ""
	}
}

func mapCommandToWindowsPackage(command string) string {
	switch command {
	case "php":
		return "PHP.PHP.8.3"
	case "composer":
		return "Composer.Composer"
	case "git":
		return "Git.Git"
	case "node", "npm":
		return "OpenJS.NodeJS.LTS"
	case "docker":
		return "Docker.DockerDesktop"
	case "php-version", "php-ext-ctype", "php-ext-curl", "php-ext-dom", "php-ext-fileinfo", "php-ext-mbstring", "php-ext-openssl", "php-ext-pdo", "php-ext-pdo_mysql", "php-ext-tokenizer", "php-ext-xml", "php-ext-zip":
		return "PHP.PHP.8.3"
	default:
		return ""
	}
}
