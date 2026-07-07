package main

import (
	"io"
	"os"
	"path/filepath"
	"strings"
)

func copyDir(source string, target string) error {
	return filepath.WalkDir(source, func(path string, entry os.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}

		relative, err := filepath.Rel(source, path)
		if err != nil {
			return err
		}

		if shouldSkipSourceCopy(relative) {
			if entry.IsDir() {
				return filepath.SkipDir
			}

			return nil
		}

		targetPath := filepath.Join(target, relative)
		info, err := entry.Info()
		if err != nil {
			return err
		}

		if entry.IsDir() {
			return os.MkdirAll(targetPath, info.Mode())
		}

		return copyFile(path, targetPath, info.Mode())
	})
}

func shouldSkipSourceCopy(relative string) bool {
	path := filepath.ToSlash(relative)

	if path == "." {
		return false
	}

	ignoredExact := map[string]bool{
		".agents":               true,
		".ai":                   true,
		".codex":                true,
		".env":                  true,
		".git":                  true,
		".gemini":               true,
		".opencode":             true,
		".phpunit.cache":        true,
		"AGENTS.md":             true,
		"GEMINI.md":             true,
		"node_modules":          true,
		"opencode.json":         true,
		"public/build":          true,
		"public/hot":            true,
		"public/storage":        true,
		"storage/logs":          true,
		"tools/rwsoft-cli/dist": true,
		"vendor":                true,
	}

	if ignoredExact[path] {
		return true
	}

	if strings.HasPrefix(path, ".env.") && path != ".env.example" {
		return true
	}

	ignoredPrefixes := []string{
		".agents/",
		".ai/",
		".codex/",
		".git/",
		".gemini/",
		".opencode/",
		".phpunit.cache/",
		"node_modules/",
		"public/build/",
		"public/storage/",
		"storage/logs/",
		"tools/rwsoft-cli/dist/",
		"vendor/",
	}

	for _, prefix := range ignoredPrefixes {
		if strings.HasPrefix(path, prefix) {
			return true
		}
	}

	if strings.HasPrefix(path, "bootstrap/cache/") && strings.HasSuffix(path, ".php") {
		return true
	}

	return false
}

func copyFile(source string, target string, mode os.FileMode) error {
	if err := os.MkdirAll(filepath.Dir(target), 0755); err != nil {
		return err
	}

	in, err := os.Open(source)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.OpenFile(target, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode)
	if err != nil {
		return err
	}
	defer out.Close()

	_, err = io.Copy(out, in)
	return err
}
