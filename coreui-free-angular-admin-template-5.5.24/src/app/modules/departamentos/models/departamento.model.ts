export interface Departamento {
  dep_id?: number;
  dep_descri: string;
  dep_estado?: number;
  dep_creado?: string;

  // calculado por el backend
  ciudades_count?: number;
}