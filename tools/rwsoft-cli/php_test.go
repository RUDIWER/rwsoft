package main

import "testing"

func TestCompareVersion(t *testing.T) {
	cases := []struct {
		left  string
		right string
		want  int
	}{
		{left: "8.3.0", right: "8.3.0", want: 0},
		{left: "8.4.1", right: "8.3.0", want: 1},
		{left: "8.2.99", right: "8.3.0", want: -1},
		{left: "8.3.0-dev", right: "8.3.0", want: 0},
	}

	for _, test := range cases {
		if got := compareVersion(test.left, test.right); got != test.want {
			t.Fatalf("compareVersion(%q, %q) = %d, want %d", test.left, test.right, got, test.want)
		}
	}
}
