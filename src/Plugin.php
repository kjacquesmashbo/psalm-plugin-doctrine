<?php

namespace Weirdan\DoctrinePsalmPlugin;

use Composer\Semver\Semver;
use OutOfBoundsException;
use PackageVersions\Versions;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;
use Weirdan\DoctrinePsalmPlugin\Provider\ReturnTypeProvider\CollectionFirstAndLast;
use function preg_grep;
use function version_compare;

use const PREG_GREP_INVERT;

use function array_merge;
use function class_exists;
use function explode;
use function glob;
use function strpos;

class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $psalm, ?SimpleXMLElement $config = null): void
    {
        foreach ($this->getStubFiles() as $file) {
            $psalm->addStubFile($file);
        }

        if (class_exists(CollectionFirstAndLast::class)) {
            $psalm->registerHooksFromClass(CollectionFirstAndLast::class);
        }
    }

    /** @return string[] */
    private function getStubFiles(): array
    {
        $files = glob(__DIR__ . '/../stubs/*.phpstub') ?: [];

        if ($this->hasPackage('doctrine/collections')) {
            [$ver] = explode('@', $this->getPackageVersion('doctrine/collections'));
            if (version_compare($ver, 'v1.6.0', '>=')) {
                $files = preg_grep('/Collections\.phpstub$/', $files, PREG_GREP_INVERT);
            }
        }

        return array_merge($files, glob(__DIR__ . '/../stubs/DBAL/*.phpstub') ?: []);
    }

    private function hasPackage(string $packageName): bool
    {
        try {
            $this->getPackageVersion($packageName);
        } catch (OutOfBoundsException $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws OutOfBoundsException
     *
     * @psalm-suppress UnusedParam
     */
    private function getPackageVersion(string $packageName): string
    {
        if (class_exists(Versions::class)) {
            return (string) Versions::getVersion($packageName);
        }

        throw new OutOfBoundsException();
    }

    private function hasPackageOfVersion(string $packageName, string $constraints): bool
    {
        $packageVersion = $this->getPackageVersion($packageName);
        if (strpos($packageVersion, '@') !== false) {
            [$packageVersion] = explode('@', $packageVersion);
        }

        if (strpos($packageVersion, 'dev-') === 0) {
            $packageVersion = '9999999-dev';
        }

        return Semver::satisfies($packageVersion, $constraints);
    }
}
