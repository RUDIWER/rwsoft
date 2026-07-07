package main

import (
	"context"
	"fmt"
)

func InstallWithDocker(ctx context.Context, runner Runner, options InstallOptions, envConfig EnvConfig) error {
	docker := DockerCompose{Runner: runner}

	if err := docker.Run(ctx, "build"); err != nil {
		return err
	}

	if err := docker.Run(ctx, "up", "-d", "mysql"); err != nil {
		return err
	}

	if !options.SkipComposer {
		if err := docker.Run(ctx, "run", "--rm", "app", "composer", "install"); err != nil {
			return err
		}
	}

	if !options.SkipNPM {
		if err := docker.Run(ctx, "run", "--rm", "app", "npm", "install"); err != nil {
			return err
		}
	}

	if !options.SkipArtisan {
		if err := docker.Run(ctx, "run", "--rm", "app", "php", "artisan", "key:generate", "--force"); err != nil {
			return err
		}

		artisanArgs := []string{"run", "--rm", "app", "php", "artisan"}
		artisanArgs = append(artisanArgs, ArtisanInstallArgs(options.Profile, options.PlatformAdminEmail, options.Site, envConfig)...)
		if options.NoInteraction {
			artisanArgs = append(artisanArgs, "--no-interaction")
		}

		if err := docker.Run(ctx, artisanArgs...); err != nil {
			return err
		}
	}

	if err := docker.Run(ctx, "up", "-d", "app", "vite"); err != nil {
		return err
	}

	fmt.Println("Docker services are running. App: http://localhost")

	return nil
}

type DockerCompose struct {
	Runner Runner
}

func (docker DockerCompose) Run(ctx context.Context, args ...string) error {
	if commandExists("docker") {
		composeArgs := append([]string{"compose"}, args...)

		return docker.Runner.Run(ctx, "docker", composeArgs...)
	}

	if commandExists("docker-compose") {
		return docker.Runner.Run(ctx, "docker-compose", args...)
	}

	return fmt.Errorf("docker compose is required for the docker profile")
}
