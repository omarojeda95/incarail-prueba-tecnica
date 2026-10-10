<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Acceso\GestionUsuarios;
use App\Http\Controllers\Controller;
use App\Http\Requests\Acceso\CrearUsuarioRequest;
use App\Http\Requests\Acceso\EditarUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    public function __construct(private readonly GestionUsuarios $usuarios) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'por_pagina' => ['nullable', 'integer', 'between:1,100']]);

        $usuarios = Usuario::query()
            ->with('roles')
            ->when($request->query('buscar'), fn ($query, $texto) => $query->where(
                fn ($query) => $query->whereLike('nombre', "%{$texto}%")->orWhereLike('correo', "%{$texto}%")
            ))
            ->orderBy('nombre')
            ->paginate($request->integer('por_pagina', 20))
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }

    public function store(CrearUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarios->crear($request->validated());

        return (new UsuarioResource($usuario->load('roles')))->response()->setStatusCode(201);
    }

    public function show(Usuario $usuario): UsuarioResource
    {
        return new UsuarioResource($usuario->load('roles'));
    }

    public function update(EditarUsuarioRequest $request, Usuario $usuario): UsuarioResource
    {
        return new UsuarioResource($this->usuarios->editar($usuario, $request->validated())->load('roles'));
    }

    public function desactivar(Request $request, Usuario $usuario): UsuarioResource
    {
        return new UsuarioResource($this->usuarios->desactivar($usuario, $request->user())->load('roles'));
    }

    public function activar(Usuario $usuario): UsuarioResource
    {
        return new UsuarioResource($this->usuarios->activar($usuario)->load('roles'));
    }
}
