package main

import "fmt"

type Check struct {
	Name     string
	Required bool
	OK       bool
	Detail   string
}

type DoctorReport struct {
	Profile Profile
	Checks  []Check
}

func inspectSystem(profile Profile) DoctorReport {
	checks := []Check{
		commandCheck("php", profile.RequiresPHP),
		commandCheck("composer", profile.RequiresComposer),
		commandCheck("git", profile.RequiresGit),
		commandCheck("node", profile.RequiresNode),
		commandCheck("npm", profile.RequiresNode),
		commandCheck("docker", profile.RequiresDocker),
	}

	if profile.RequiresPHP {
		checks = append(checks, phpVersionCheck(true))
		for _, extension := range requiredPHPExtensions {
			checks = append(checks, phpExtensionCheck(extension, true))
		}
	}

	return DoctorReport{Profile: profile, Checks: checks}
}

func commandCheck(name string, required bool) Check {
	path, ok := lookupCommand(name)
	if ok {
		return Check{Name: name, Required: required, OK: true, Detail: path}
	}

	return Check{Name: name, Required: required, OK: false, Detail: "not found"}
}

func (r DoctorReport) Print() {
	fmt.Printf("RwSoft doctor\nProfile: %s\n\n", r.Profile.Name)
	for _, check := range r.Checks {
		status := "OK"
		if !check.OK && check.Required {
			status = "MISSING"
		} else if !check.OK {
			status = "optional missing"
		}

		required := "optional"
		if check.Required {
			required = "required"
		}

		fmt.Printf("[%s] %s (%s): %s\n", status, check.Name, required, check.Detail)
	}
}

func (r DoctorReport) HasRequiredFailures() bool {
	for _, check := range r.Checks {
		if check.Required && !check.OK {
			return true
		}
	}

	return false
}
