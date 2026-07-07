<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Exception;

use InvalidArgumentException;

/**
 * A permission map that cannot be what the author meant: empty names, an
 * unknown tool class, or #[RequiredPermission] on a method that is not a
 * tool (fail-fast — a silently unenforced permission is a security bug).
 *
 * @api
 */
final class InvalidPermissionMapException extends InvalidArgumentException {}
