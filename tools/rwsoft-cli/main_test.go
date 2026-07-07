package main

import (
	"bytes"
	"io"
	"os"
	"strings"
	"testing"
)

func TestUsageIncludesPlatformAdminEmailOption(t *testing.T) {
	output := captureStdout(t, printUsage)

	if !strings.Contains(output, "--platform-admin-email=admin@example.com") {
		t.Fatalf("usage does not include platform admin option:\n%s", output)
	}
}

func captureStdout(t *testing.T, callback func()) string {
	t.Helper()

	oldStdout := os.Stdout
	reader, writer, err := os.Pipe()
	if err != nil {
		t.Fatalf("create stdout pipe: %v", err)
	}

	os.Stdout = writer
	callback()
	writer.Close()
	os.Stdout = oldStdout

	var buffer bytes.Buffer
	if _, err := io.Copy(&buffer, reader); err != nil {
		t.Fatalf("read stdout pipe: %v", err)
	}

	return buffer.String()
}
