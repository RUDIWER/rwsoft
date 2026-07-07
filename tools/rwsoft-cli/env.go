package main

import (
	"bufio"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

type EnvConfig struct {
	AppURL         string
	DBConnection   string
	DBHost         string
	DBPort         string
	DBDatabase     string
	DBUsername     string
	DBPassword     string
	TenantStorage  string
	SharedDatabase string
}

func ResolveEnvConfig(profile Profile, input EnvConfig, noInteraction bool) (EnvConfig, error) {
	config := EnvConfig{
		AppURL:         firstNonEmpty(input.AppURL, profile.DefaultAppURL),
		DBConnection:   firstNonEmpty(input.DBConnection, profile.DefaultDB),
		DBHost:         firstNonEmpty(input.DBHost, profile.DefaultDBHost),
		DBPort:         firstNonEmpty(input.DBPort, profile.DefaultDBPort),
		DBDatabase:     firstNonEmpty(input.DBDatabase, profile.DefaultDBName),
		DBUsername:     firstNonEmpty(input.DBUsername, profile.DefaultDBUser),
		DBPassword:     firstNonEmpty(input.DBPassword, profile.DefaultDBPassword),
		TenantStorage:  firstNonEmpty(input.TenantStorage, profile.TenantStorage),
		SharedDatabase: input.SharedDatabase,
	}

	if config.TenantStorage == "shared_prefixed" && config.SharedDatabase == "" {
		config.SharedDatabase = config.DBDatabase
	}

	if noInteraction || !isInteractiveTerminal() {
		return config, validateEnvConfig(config)
	}

	reader := bufio.NewReader(os.Stdin)
	var err error
	if config.AppURL, err = prompt(reader, "APP_URL", config.AppURL); err != nil {
		return config, err
	}
	if config.DBConnection, err = prompt(reader, "DB_CONNECTION", config.DBConnection); err != nil {
		return config, err
	}
	if config.DBHost, err = prompt(reader, "DB_HOST", config.DBHost); err != nil {
		return config, err
	}
	if config.DBPort, err = prompt(reader, "DB_PORT", config.DBPort); err != nil {
		return config, err
	}
	if config.DBDatabase, err = prompt(reader, "DB_DATABASE", config.DBDatabase); err != nil {
		return config, err
	}
	if config.DBUsername, err = prompt(reader, "DB_USERNAME", config.DBUsername); err != nil {
		return config, err
	}
	if config.DBPassword, err = prompt(reader, "DB_PASSWORD", config.DBPassword); err != nil {
		return config, err
	}
	if config.TenantStorage, err = prompt(reader, "Tenant storage (create_database, existing_database, shared_prefixed)", config.TenantStorage); err != nil {
		return config, err
	}
	if config.TenantStorage == "shared_prefixed" {
		if config.SharedDatabase == "" {
			config.SharedDatabase = config.DBDatabase
		}
		if config.SharedDatabase, err = prompt(reader, "TENANCY_SHARED_DATABASE", config.SharedDatabase); err != nil {
			return config, err
		}
	}

	return config, validateEnvConfig(config)
}

func EnsureEnvFile(targetDir string, profile Profile, envConfig EnvConfig, dryRun bool) error {
	envPath := filepath.Join(targetDir, ".env")
	if _, err := os.Stat(envPath); err == nil {
		fmt.Println(".env already exists; leaving it unchanged")
		return nil
	}

	examplePath := filepath.Join(targetDir, ".env.example")
	contents, err := os.ReadFile(examplePath)
	if err != nil {
		if dryRun && os.IsNotExist(err) {
			fmt.Printf("DRY-RUN: create %s from .env.example after project checkout\n", envPath)
			return nil
		}

		return fmt.Errorf("read .env.example: %w", err)
	}

	updated := applyEnvDefaults(string(contents), map[string]string{
		"APP_URL":                   envConfig.AppURL,
		"RWSOFT_INSTALL_PROFILE":    profile.Name,
		"DB_CONNECTION":             envConfig.DBConnection,
		"DB_HOST":                   envConfig.DBHost,
		"DB_PORT":                   envConfig.DBPort,
		"DB_DATABASE":               envConfig.DBDatabase,
		"DB_USERNAME":               envConfig.DBUsername,
		"DB_PASSWORD":               envConfig.DBPassword,
		"TENANCY_DATABASE_MODE":     databaseModeForStorage(envConfig.TenantStorage),
		"TENANCY_PROVISIONING_MODE": envConfig.TenantStorage,
		"TENANCY_SHARED_DATABASE":   envConfig.SharedDatabase,
	})

	if dryRun {
		fmt.Printf("DRY-RUN: create %s from .env.example\n", envPath)
		return nil
	}

	return os.WriteFile(envPath, []byte(updated), 0600)
}

func applyEnvDefaults(input string, values map[string]string) string {
	seen := map[string]bool{}
	var lines []string
	scanner := bufio.NewScanner(strings.NewReader(input))
	for scanner.Scan() {
		line := scanner.Text()
		trimmed := strings.TrimSpace(line)
		if trimmed == "" || strings.HasPrefix(trimmed, "#") || !strings.Contains(line, "=") {
			lines = append(lines, line)
			continue
		}

		key := strings.TrimSpace(strings.SplitN(line, "=", 2)[0])
		if value, ok := values[key]; ok {
			lines = append(lines, key+"="+value)
			seen[key] = true
			continue
		}

		lines = append(lines, line)
	}

	for key, value := range values {
		if !seen[key] && value != "" {
			lines = append(lines, key+"="+value)
		}
	}

	return strings.Join(lines, "\n") + "\n"
}

func databaseModeForStorage(storage string) string {
	if storage == "shared_prefixed" {
		return "shared_prefixed"
	}

	return "separate"
}

func prompt(reader *bufio.Reader, label string, defaultValue string) (string, error) {
	if defaultValue != "" {
		fmt.Printf("%s [%s]: ", label, defaultValue)
	} else {
		fmt.Printf("%s: ", label)
	}

	value, err := reader.ReadString('\n')
	if err != nil {
		return "", err
	}

	value = strings.TrimSpace(value)
	if value == "" {
		return defaultValue, nil
	}

	return value, nil
}

func validateEnvConfig(config EnvConfig) error {
	if config.AppURL == "" {
		return fmt.Errorf("APP_URL is required")
	}

	if config.DBConnection == "" {
		return fmt.Errorf("DB_CONNECTION is required")
	}

	if config.DBConnection != "sqlite" {
		if config.DBHost == "" || config.DBPort == "" || config.DBDatabase == "" || config.DBUsername == "" {
			return fmt.Errorf("database host, port, database and username are required for %s", config.DBConnection)
		}
	}

	switch config.TenantStorage {
	case "create_database", "existing_database", "shared_prefixed":
		return nil
	default:
		return fmt.Errorf("unsupported tenant storage mode %q", config.TenantStorage)
	}
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}

	return ""
}

func isInteractiveTerminal() bool {
	info, err := os.Stdin.Stat()
	if err != nil {
		return false
	}

	return (info.Mode() & os.ModeCharDevice) != 0
}
