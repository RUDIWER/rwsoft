package main

import (
	"fmt"
	"os/exec"
	"strconv"
	"strings"
)

var requiredPHPExtensions = []string{
	"ctype",
	"curl",
	"dom",
	"fileinfo",
	"mbstring",
	"openssl",
	"pdo",
	"pdo_mysql",
	"tokenizer",
	"xml",
	"zip",
}

func phpVersionCheck(required bool) Check {
	if !commandExists("php") {
		return Check{Name: "php-version", Required: required, OK: false, Detail: "php not found"}
	}

	version, err := commandOutput("php", "-r", "echo PHP_VERSION;")
	if err != nil {
		return Check{Name: "php-version", Required: required, OK: false, Detail: err.Error()}
	}

	version = strings.TrimSpace(version)
	if compareVersion(version, "8.3.0") < 0 {
		return Check{Name: "php-version", Required: required, OK: false, Detail: fmt.Sprintf("%s found, >= 8.3.0 required", version)}
	}

	return Check{Name: "php-version", Required: required, OK: true, Detail: version}
}

func phpExtensionCheck(extension string, required bool) Check {
	name := "php-ext-" + extension
	if !commandExists("php") {
		return Check{Name: name, Required: required, OK: false, Detail: "php not found"}
	}

	output, err := commandOutput("php", "-r", fmt.Sprintf("echo extension_loaded('%s') ? 'yes' : 'no';", extension))
	if err != nil {
		return Check{Name: name, Required: required, OK: false, Detail: err.Error()}
	}

	if strings.TrimSpace(output) != "yes" {
		return Check{Name: name, Required: required, OK: false, Detail: "missing"}
	}

	return Check{Name: name, Required: required, OK: true, Detail: "loaded"}
}

func commandOutput(name string, args ...string) (string, error) {
	output, err := exec.Command(name, args...).CombinedOutput()
	return string(output), err
}

func compareVersion(left string, right string) int {
	leftParts := versionParts(left)
	rightParts := versionParts(right)

	for i := 0; i < 3; i++ {
		if leftParts[i] < rightParts[i] {
			return -1
		}
		if leftParts[i] > rightParts[i] {
			return 1
		}
	}

	return 0
}

func versionParts(version string) [3]int {
	var parts [3]int
	segments := strings.Split(version, ".")
	for i := 0; i < len(segments) && i < 3; i++ {
		segment := ""
		for _, char := range segments[i] {
			if char < '0' || char > '9' {
				break
			}
			segment += string(char)
		}

		value, err := strconv.Atoi(segment)
		if err == nil {
			parts[i] = value
		}
	}

	return parts
}
