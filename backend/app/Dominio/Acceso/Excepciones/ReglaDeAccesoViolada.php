<?php

namespace App\Dominio\Acceso\Excepciones;

use RuntimeException;

/** Un cambio de usuario o rol que rompería una regla de acceso (p. ej. quedarse sin administradores). */
final class ReglaDeAccesoViolada extends RuntimeException {}
