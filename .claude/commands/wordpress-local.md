-I'm working on a WordPress site using Local (by Flywheel). The environment is fully configured with WP-CLI available.

CRITICAL - CURRENT SETUP:

- You are at the PROJECT ROOT: not in the WordPress directory
- WordPress installation is in: ./app/public/
- PHP error logs are in: ./logs/php/error.log
- To run WP-CLI commands, you MUST EITHER:
  a) cd app/public && wp [command]
  b) Use: (cd app/public && wp [command])

BUILD ISSUES:

- NODE_ENV is already set to: development
- For npm builds, always ensure NODE_ENV=development

DIRECTORY STRUCTURE:
.
├── app/
│ └── public/ # WordPress root (WHERE WP-CLI WORKS)
│ ├── wp-content/
│ ├── wp-config.php
│ └── ...
├── logs/
│ ├── nginx/
│ └── php/
│ └── error.log # PHP errors
└── conf/ # Local configuration

WORKING WITH WP-CLI FROM PROJECT ROOT:
Since we're at project root, use subshell for WP-CLI:

- (cd app/public && wp plugin list)
- (cd app/public && wp cache flush)
- (cd app/public && wp db query "SELECT \* FROM wp_options LIMIT 5")

EXAMPLES:

# Check logs from project root

cat logs/php/error.log

# Run WP-CLI from project root

(cd app/public && wp plugin list --status=active)

# Work on a plugin

cd app/public/wp-content/plugins/my-plugin
npm install

Please acknowledge that you understand you're at the project root and must run WP-CLI commands from app/public.
