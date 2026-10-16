<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los dos catalogos del Paso 1/3, que son INDEPENDIENTES entre
 * si: tipo_profesion guarda la RELACION LABORAL y profesion guarda el
 * OFICIO. No hay FK entre ambos -- cualquier combinacion es valida (un
 * Medico puede ser funcionario publico, privado o independiente), y el
 * formulario muestra las dos listas completas sin filtrar una por otra.
 *
 * updateOrInsert y no insert: el seeder tiene que poder correrse de
 * nuevo sobre una base ya cargada sin romper los UNIQUE.
 */
class ProfesionSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarTiposProfesion();
        $this->sembrarProfesiones();
    }

    /** Relacion laboral declarada por el solicitante. */
    private function sembrarTiposProfesion(): void
    {
        $tipos = [
            'Funcionario Público',
            'Funcionario Privado',
            'Independiente',
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_profesion')->updateOrInsert(
                ['tpr_descri' => $tipo],
                ['tpr_estado' => 1, 'tpr_creado' => now()]
            );
        }
    }

    /** Catalogo plano de oficios y profesiones. */
    private function sembrarProfesiones(): void
    {
        $profesiones = [
            'Abogados',
            'Administrador',
            'Albañiles',
            'Analista',
            'Arquitectos',
            'Artista',
            'Auditor',
            'Cajeros',
            'Carpinteros',
            'Chofer',
            'Consultores',
            'Contador',
            'Diseñadores',
            'Docente',
            'Electricistas',
            'Encargado/Jefe',
            'Entrenadores y Preparadores Físicos',
            'Estilistas, Esteticistas y Afines',
            'Fotógrafos',
            'Gerentes',
            'Guardia de Seguridad',
            'Guías de Viaje y Turismo',
            'Herreros',
            'Informático',
            'Ingenieros',
            'Inspectores',
            'Jardineros',
            'Jubilados',
            'Licenciado en Salud',
            'Limpiador',
            'Mecánicos',
            'Meseros y Capitán de Meseros',
            'Miembro de Tripulación',
            'Médicos',
            'Nutricionista',
            'Obreros y Ayudantes',
            'Odontólogos',
            'Oficial de Cuentas',
            'Operador',
            'Organizadores de Eventos',
            'Pilotos',
            'Policías',
            'Profesional Gastronómico',
            'Psicólogos',
            'Químicos',
            'Secretario/Asistente',
            'Supervisor',
            'Traductores e Intérpretes',
            'Técnico',
            'Vendedor',
            'Veterinarios',
        ];

        foreach ($profesiones as $profesion) {
            DB::table('profesion')->updateOrInsert(
                ['pro_descri' => $profesion],
                ['pro_estado' => 1, 'pro_creado' => now()]
            );
        }
    }
}