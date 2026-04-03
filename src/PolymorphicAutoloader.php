<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Exception\MappingException;

/**
 * @internal
 */
final readonly class PolymorphicAutoloader
{
    public static function register(string $referencesDir, string $referencesNamespace): \Closure
    {
        $autoloader = static function (string $className) use ($referencesDir, $referencesNamespace): void {
            if (!str_starts_with($className, $referencesNamespace)) {
                return;
            }
            $shortClassName = ltrim(mb_substr($className, mb_strlen($referencesNamespace)), '\\');
            $filePath = $referencesDir
                        .\DIRECTORY_SEPARATOR
                        .str_replace('\\', \DIRECTORY_SEPARATOR, $shortClassName)
                        .'.php';
            if (!file_exists($filePath)) {
                throw MappingException::referenceClassNotGenerated($filePath);
            }
            require_once $filePath;
        };
        spl_autoload_register($autoloader, true, true);

        return $autoloader;
    }
}
