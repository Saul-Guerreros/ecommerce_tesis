import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { MedioResultadoCuota, ResultadoPagoCuota } from '../../../core/models/cuota-pago.model';
import { MisCuotasService } from '../../../core/services/mis-cuotas.service';

/**
 * Pantalla final del pago de cuotas.
 *
 * Vive en su propia ruta y toma el id de la declaración de la URL, así
 * que es válida de forma permanente: el cliente puede recargarla o
 * volver con el botón del navegador sin encontrarse una página muerta.
 *
 * Los casos los clasifica el backend (campo 'caso'), no esta pantalla:
 * dependen de qué métodos exigen comprobante, que es un dato del
 * catálogo metodo_pago.
 */
@Component({
  selector: 'app-pago-cuota-resultado',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DatePipe],
  templateUrl: './pago-cuota-resultado.component.html',
  styleUrl: './pago-cuota-resultado.component.css',
})
export class PagoCuotaResultadoComponent implements OnInit {
  numero = '';
  cpaId = 0;
  resultado!: ResultadoPagoCuota;
  cargando = true;

  constructor(
    private servicio: MisCuotasService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.cpaId = Number(this.route.snapshot.paramMap.get('cpaId') ?? 0);

    this.servicio.resultadoPago(this.cpaId).subscribe({
      next: (resp) => {
        this.resultado = resp.data;
        this.cargando = false;
      },
      error: (e) => {
        this.cargando = false;
        this.toastr.error(e.error?.message ?? 'No encontramos ese pago.', 'Error');
        this.router.navigate(['/mis-cuotas'], { replaceUrl: true });
      },
    });
  }

  // ---------- Composición del pago ----------

  get mediosConComprobante(): MedioResultadoCuota[] {
    return this.resultado.medios.filter((m) => m.tiene_comprobante);
  }

  get mediosEfectivo(): MedioResultadoCuota[] {
    return this.resultado.medios.filter((m) => m.mpa_codigo === 'CONTRAENTREGA');
  }

  get variosComprobantes(): boolean {
    return this.mediosConComprobante.length > 1;
  }

  get variosCheques(): boolean {
    return (this.resultado?.cheque?.cheques.length ?? 0) > 1;
  }

  /** Hubo algo que enviar, o sólo algo que elegir. */
  get hayComprobante(): boolean {
    return this.mediosConComprobante.length > 0;
  }

  get titulo(): string {
    return this.hayComprobante ? 'Comprobante enviado' : 'Pago seleccionado';
  }

  /** Cuotas cubiertas: "N° 1 y 2". */
  get numerosCuotas(): string {
    const numeros = this.resultado.cuotas.map((c) => c.numero);

    if (numeros.length <= 1) return `N° ${numeros[0] ?? ''}`;

    return `N° ${numeros.slice(0, -1).join(', ')} y ${numeros[numeros.length - 1]}`;
  }

  /** "Pago único" con un solo medio; "Primer pago", "Segundo pago"... si no. */
  rotulo(medio: MedioResultadoCuota): string {
    if (this.resultado.medios.length === 1) return 'Pago único';

    const nombres = ['Primer', 'Segundo', 'Tercer', 'Cuarto', 'Quinto',
      'Sexto', 'Séptimo', 'Octavo', 'Noveno', 'Décimo'];

    return `${nombres[medio.orden - 1] ?? `Pago ${medio.orden}`} pago`;
  }

  ordinalCheque(orden: number): string {
    const nombres = ['primer', 'segundo', 'tercer', 'cuarto', 'quinto'];

    return nombres[orden - 1] ?? `cheque ${orden}`;
  }

  // ---------- Navegación ----------

  volverAlDetalle(): void {
    this.router.navigate(['/mis-cuotas', this.numero]);
  }

  irAMisCuotas(): void {
    this.router.navigate(['/mis-cuotas']);
  }

  volverATienda(): void {
    this.router.navigate(['/']);
  }
}