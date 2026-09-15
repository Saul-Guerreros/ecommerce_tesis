export interface TipoAjuste {
  tpa_id?: number;
  tpa_codig: string;
  tpa_descri: string;
  tpa_natura: 'P' | 'N';
  tpa_reqcom?: number;
  tpa_estado?: number;
  usos_count?: number;
}