<?php

namespace App\Exceptions;

use RuntimeException;

/** Erreur métier dont le message (en français) peut être affiché à l'utilisateur. */
class BusinessException extends RuntimeException {}
