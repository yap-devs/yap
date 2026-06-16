import {existsSync} from 'node:fs';

function isWindowsMount(path) {
  return /^\/mnt\/[a-z]\//i.test(path);
}

function getProjectRoot({project, directory, worktree}) {
  return worktree || project?.path?.root || project?.root || directory || process.cwd();
}

function hasBoostInstalled(root) {
  return existsSync(`${root}/vendor/laravel/boost/composer.json`);
}

export default async (context) => ({
  config: (config) => {
    const root = getProjectRoot(context);
    const boost = config.mcp?.['laravel-boost'];

    if (!boost || !hasBoostInstalled(root)) {
      return;
    }

    boost.enabled = process.env.OPENCODE_LARAVEL_BOOST !== '0';

    if (boost.enabled && isWindowsMount(root)) {
      boost.command = ['php.exe', ...boost.command.slice(1)];
    }
  },
});
