<?php
// Returns method signature as JSON for a static method like "App\\Support\\Network::isValid".

require __DIR__ . '/../vendor/autoload.php';

$spec = $argv[1] ?? '';
if (!str_contains($spec, '::')) {
    echo json_encode(null);
    exit(0);
}

[$class, $method] = explode('::', $spec, 2);

try {
    $ref = new ReflectionMethod($class, $method);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(0);
}

function typeToString(?ReflectionType $type): string
{
    if ($type === null) {
        return 'mixed';
    }

    $types = [];
    $nullable = $type->allowsNull();

    if ($type instanceof ReflectionUnionType) {
        foreach ($type->getTypes() as $t) {
            $types[] = $t instanceof ReflectionNamedType ? $t->getName() : (string) $t;
        }
    } elseif ($type instanceof ReflectionIntersectionType) {
        foreach ($type->getTypes() as $t) {
            $types[] = (string) $t;
        }
    } elseif ($type instanceof ReflectionNamedType) {
        $types[] = $type->getName();
    } else {
        $types[] = (string) $type;
    }

    $types = array_unique($types);

    if (in_array('mixed', $types, true)) {
        return 'mixed';
    }

    if ($nullable && !in_array('null', $types, true)) {
        $types[] = 'null';
    }

    return implode('|', $types) ?: 'mixed';
}

$params = [];
foreach ($ref->getParameters() as $param) {
    $params[] = [
        'name' => $param->getName(),
        'type' => typeToString($param->getType()),
        'position' => $param->getPosition(),
        'optional' => $param->isOptional(),
    ];
}

echo json_encode([
    'class' => $ref->getDeclaringClass()->getName(),
    'method' => $ref->getName(),
    'params' => $params,
    'return' => typeToString($ref->getReturnType()),
], JSON_UNESCAPED_SLASHES);
