<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

final class TemplateLocator
{
    /**
     * @param list<string> $themePaths active theme first, then parents
     * @param list<string> $extensions
     */
    public function resolve(
        array $themePaths,
        array $candidates,
        string $directory = 'templates',
        array $extensions = ['twig', 'php', 'html'],
    ): ?ResolvedTemplate {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || in_array('..', explode('/', $directory), true)) {
            throw new \InvalidArgumentException('Unsafe template directory.');
        }

        foreach ($candidates as $candidate) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,190}$/', $candidate) !== 1) {
                continue;
            }

            foreach ($themePaths as $index => $themePath) {
                $root = rtrim(str_replace('\\', '/', $themePath), '/');
                foreach ($extensions as $extension) {
                    if (preg_match('/^[a-z0-9]{1,12}$/', $extension) !== 1) {
                        continue;
                    }

                    $file = $root . '/' . $directory . '/' . $candidate . '.' . $extension;
                    if (is_file($file)) {
                        return new ResolvedTemplate($candidate, $file, $root, (int)$index);
                    }
                }
            }
        }

        return null;
    }
}
