package main

type SiteConfig struct {
	Name           string
	Slug           string
	Domain         string
	AdminEmail     string
	TenantDatabase string
	TenantPrefix   string
	Skip           bool
}

func (config SiteConfig) ArtisanArgs() []string {
	var args []string

	if config.Skip {
		args = append(args, "--skip-site")
	}

	if config.Name != "" {
		args = append(args, "--site-name="+config.Name)
	}

	if config.Slug != "" {
		args = append(args, "--site-slug="+config.Slug)
	}

	if config.Domain != "" {
		args = append(args, "--site-domain="+config.Domain)
	}

	if config.AdminEmail != "" {
		args = append(args, "--site-admin-email="+config.AdminEmail)
	}

	if config.TenantDatabase != "" {
		args = append(args, "--site-tenant-database="+config.TenantDatabase)
	}

	if config.TenantPrefix != "" {
		args = append(args, "--site-tenant-prefix="+config.TenantPrefix)
	}

	return args
}
