<?php

namespace Esign\HelperModelTranslatable\Exceptions;

use Exception;

class InvalidConfiguration extends Exception
{
    public static function helperModelNotFound(string $helperModel, array $namespaces): self
    {
        $consideredNamespaces = implode(', ', $namespaces);

        return new static("Failed to find helper model `{$helperModel}` in namespaces [{$consideredNamespaces}]");
    }
}
