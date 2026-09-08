<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\ContactoProveedorRequest;
use App\Http\Resources\Compra\ContactoProveedorResource;
use App\Models\Compras\ContactoProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactoProveedorController extends Controller
{
    // GET /api/admin/contactos-proveedor?proveedor=<pro_id>  (obligatorio)
    public function index(Request $request): JsonResponse
    {
        $request->validate(['proveedor' => ['required', 'integer', 'exists:proveedor,pro_id']]);

        $contactos = ContactoProveedor::where('cpr_proid', $request->proveedor)
            ->orderByDesc('cpr_princi')
            ->orderBy('cpr_nombre')
            ->get();

        return response()->json(['data' => ContactoProveedorResource::collection($contactos)], 200);
    }

    // GET /api/admin/contactos-proveedor/{id}
    public function show(int $id): JsonResponse
    {
        $contacto = ContactoProveedor::find($id);
        if (!$contacto) return response()->json(['message' => 'Contacto no encontrado'], 404);

        return response()->json(['data' => new ContactoProveedorResource($contacto)], 200);
    }

    // POST /api/admin/contactos-proveedor
    public function store(ContactoProveedorRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['cpr_estado'] = $datos['cpr_estado'] ?? 1;
        $datos['cpr_princi'] = $datos['cpr_princi'] ?? 0;

        $contacto = ContactoProveedor::create($datos);

        return response()->json([
            'message' => 'Contacto registrado correctamente',
            'data'    => new ContactoProveedorResource($contacto),
        ], 201);
    }

    // PUT /api/admin/contactos-proveedor/{id}
    public function update(ContactoProveedorRequest $request, int $id): JsonResponse
    {
        $contacto = ContactoProveedor::find($id);
        if (!$contacto) return response()->json(['message' => 'Contacto no encontrado'], 404);

        $contacto->update($request->validated());

        return response()->json([
            'message' => 'Contacto actualizado correctamente',
            'data'    => new ContactoProveedorResource($contacto->fresh()),
        ], 200);
    }

    // DELETE /api/admin/contactos-proveedor/{id}
    // Sin regla de dependencias: un contacto no es referenciado por nada mas.
    public function destroy(int $id): JsonResponse
    {
        $contacto = ContactoProveedor::find($id);
        if (!$contacto) return response()->json(['message' => 'Contacto no encontrado'], 404);

        $contacto->delete();

        return response()->json(['message' => 'Contacto eliminado correctamente'], 200);
    }

    // PATCH /api/admin/contactos-proveedor/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $contacto = ContactoProveedor::find($id);
        if (!$contacto) return response()->json(['message' => 'Contacto no encontrado'], 404);

        $contacto->cpr_estado = $contacto->cpr_estado == 1 ? 0 : 1;
        $contacto->save();

        return response()->json([
            'message' => $contacto->cpr_estado == 1 ? 'Contacto activado correctamente' : 'Contacto desactivado correctamente',
            'data'    => new ContactoProveedorResource($contacto),
        ], 200);
    }
}