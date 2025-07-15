<?php

namespace Pechynho\PolymorphicDoctrine;

use App\Exception\RuntimeException;
use Closure;

final readonly class PolymorphicAutoloader
{
    public static function register(string $referencesDir, string $referencesNamespace): Closure
    {
        $autoloader = static function (string $className) use ($referencesDir, $referencesNamespace): void {
            if (!str_starts_with($className, $referencesNamespace)) {
                return;
            }
            $shortClassName = mb_substr($className, mb_strlen($referencesNamespace));
            $filePath = $referencesDir
                        . DIRECTORY_SEPARATOR
                        . str_replace('\\', DIRECTORY_SEPARATOR, $shortClassName)
                        . '.php';
            if (!file_exists($filePath)) {
                throw new RuntimeException(
                    sprintf(
                        'Polymorphic reference class "%s" not found in "%s". Run "pechynho:polymorphic-doctrine:generate-reference-classes" command to generate it.',
                        $className,
                        $filePath,
                    ),
                );
            }
            require_once $filePath;
        };
        spl_autoload_register($autoloader, true, true);
        return $autoloader;
    }
}
