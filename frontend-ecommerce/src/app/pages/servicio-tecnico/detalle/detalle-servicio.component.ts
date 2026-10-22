import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ServicioCliente } from '../../../core/models/servicio-tecnico.model';
import { ServicioTecnicoService } from '../../../core/services/servicio-tecnico.service';

/**
 * C4 - Detalle del servicio: timeline de 5 estados, datos y presupuesto
 * con Aprobar / Rechazar (Fase 4).
 */
@Component({
  selector: 'app-detalle-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule],
  templateUrl: './detalle-servicio.component.html',
  styleUrl: './detalle-servicio.component.css',
})
export class DetalleServicioComponent implements OnInit {
  readonly pasos = ['RECIBIDO', 'EN DIAGNÓSTICO', 'EN REPARACIÓN', 'LISTO', 'ENTREGADO'];

  servicio: ServicioCliente | null = null;
  cargando = true;
  noEncontrado = false;

  // Respuesta al presupuesto
  confirmando: 'aprobar' | 'rechazar' | null = null;
  respondiendo = false;
  motivo = new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(3), Validators.maxLength(255)] });

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private service: ServicioTecnicoService,
    private toastr: ToastrService,
  ) {}

  // ---------- Paso 6: constancias y garantía ----------

  descargarConstancia(tipo: 'recepcion' | 'retiro'): void {
    if (!this.servicio) return;
    const numero = this.servicio.sse_numero;
    this.service.constancia(numero, tipo).subscribe({
      next: (pdf) => {
        const url = URL.createObjectURL(pdf);
        const a = document.createElement('a');
        a.href = url;
        a.download = `constancia-${tipo}-${numero}.pdf`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 30_000);
      },
      error: () => this.toastr.error('No se pudo descargar la constancia', 'Servicio técnico'),
    });
  }

  reclamarGarantia(): void {
    if (!this.servicio) return;
    this.router.navigate(['/servicio-tecnico/solicitar'], { queryParams: { reclamo: this.servicio.sse_numero } });
  }

  ngOnInit(): void {
    const numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.service.detalle(numero).subscribe({
      next: (res) => { this.servicio = res.data; this.cargando = false; },
      error: () => { this.noEncontrado = true; this.cargando = false; },
    });
  }

  /** La garantía de compra tiene su propio recorrido (gestión con la empresa). */
  get pasosServicio(): string[] {
    return this.servicio?.pasos ?? this.pasos;
  }

  estadoPaso(i: number): 'hecho' | 'actual' | 'pendiente' {
    const actual = this.servicio?.paso_actual ?? 0;
    if (i + 1 < actual) return 'hecho';
    if (i + 1 === actual) return 'actual';
    return 'pendiente';
  }

  // ---------- Presupuesto ----------

  get repuestos() {
    return this.servicio?.presupuesto?.lineas.filter((l) => l.tipo === 'REPUESTO') ?? [];
  }

  get manoDeObra() {
    return this.servicio?.presupuesto?.lineas.filter((l) => l.tipo === 'MANO_OBRA') ?? [];
  }

  pedirConfirmacion(accion: 'aprobar' | 'rechazar'): void {
    this.confirmando = accion;
    this.motivo.reset('');
  }

  responder(): void {
    if (!this.servicio || !this.confirmando) return;
    if (this.confirmando === 'rechazar' && this.motivo.invalid) {
      this.motivo.markAsTouched();
      return;
    }

    const numero = this.servicio.sse_numero;
    const peticion = this.confirmando === 'aprobar'
      ? this.service.aprobarPresupuesto(numero)
      : this.service.rechazarPresupuesto(numero, this.motivo.value);

    this.respondiendo = true;
    peticion.subscribe({
      next: (res) => {
        this.respondiendo = false;
        this.confirmando = null;
        this.servicio = res.data;
        this.toastr.success(res.message, 'Servicio técnico');
      },
      error: (err) => {
        this.respondiendo = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores?.['motivo']) { this.motivo.setErrors({ backend: errores['motivo'][0] }); return; }
        const msg = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo registrar tu respuesta', 'Servicio técnico');
      },
    });
  }
}
