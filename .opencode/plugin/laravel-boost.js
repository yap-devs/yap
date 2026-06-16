import {existsSync, readFileSync} from 'node:fs';

function isWindowsMount(path) {
  return /^\/mnt\/[a-z]\//i.test(path);
}

function getProjectRoot({project, directory, worktree}) {
  return worktree || project?.path?.root || project?.root || directory || process.cwd();
}

function hasBoostInstalled(root) {
  const installedPath = `${root}/vendor/composer/installed.json`;

  if (!existsSync(installedPath)) {
    return false;
  }

  const installed = JSON.parse(readFileSync(installedPath, 'utf8'));
  const packages = Array.isArray(installed) ? installed : installed.packages;

  return packages?.some((composerPackage) => composerPackage.name === 'laravel/boost') ?? false;
}

function getLaravelEnvironment(root) {
  const envPath = `${root}/.env`;

  if (!existsSync(envPath)) {
    return null;
  }

  const match = readFileSync(envPath, 'utf8').match(/^APP_ENV=(.*)$/m);
  const environment = match?.[1]?.trim().replace(/^['"]|['"]$/g, '');

  return environment || null;
}

export default async (context) => ({
  config: (config) => {
    const root = getProjectRoot(context);
    const boost = config.mcp?.['laravel-boost'];

    if (!boost) {
      return;
    }

    boost.enabled = false;

    if (getLaravelEnvironment(root) === 'production' || !hasBoostInstalled(root)) {
      return;
    }

    boost.type = 'local';
    boost.command = ['php', 'artisan', 'boost:mcp'];

    boost.enabled = process.env.OPENCODE_LARAVEL_BOOST !== '0';

    if (boost.enabled && isWindowsMount(root)) {
      boost.command = ['php.exe', ...boost.command.slice(1)];
    }
  },
});
