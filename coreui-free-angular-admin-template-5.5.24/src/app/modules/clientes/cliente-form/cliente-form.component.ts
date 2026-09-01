import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ClienteService } from '../service/cliente.service';
import { Cliente, ClienteConfig, ClientePayload } from '../models/cliente.model';

@Component({
  selector: 'app-cliente-form',
  standalone: true,
  imports: [CommonModule, FormsModule /* + módulos de CoreUI que uses */],
  templateUrl: './cliente-form.component.html',
})
export class ClienteFormComponent implements OnChanges {

  @Input() visible = false;
  @Input() cliente: Cliente | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<{ data: Cliente; isEdit: boolean }>();

  form!: ClientePayload;
  errors: any = {};
  submitting = false;
  buscando = false;
  personaEncontrada = false;

  config: ClienteConfig = {
    tipos_documento: [], tipos_cliente: [], estados_civiles: [], tipos_contacto: []
  };

  constructor(
    private clienteService: ClienteService,
    private toastr: ToastrService,
  ) {
    this.reset();
    this.cargarConfig();
  }

  get isEdit(): boolean {
    return !!this.cliente?.cli_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.reset();
      if (this.isEdit) { this.cargarCliente(); }
    }
  }

  private cargarConfig(): void {
    this.clienteService.config().subscribe({
      next: (c) => this.config = c,
      error: () => this.toastr.error('No se pudieron cargar los datos del formulario'),
    });
  }

  reset(): void {
    this.form = {
      per_tpdid: null, per_cedula: '', per_nombr1: '', per_nombr2: null,
      per_apell1: '', per_apell2: null, per_estciv: null, per_fechna: null,
      cli_tpcid: null, cli_ruc: null, cli_estado: 1,
      contactos: [{ con_tipo: 'CELULAR', con_valor: '' }],
    };
    this.errors = {};
    this.personaEncontrada = false;
    this.submitting = false;
  }

  private cargarCliente(): void {
    const c = this.cliente!;
    this.form = {
      per_id: c.persona.per_id,
      per_tpdid: c.persona.per_tpdid,
      per_cedula: c.persona.per_cedula,
      per_nombr1: c.persona.per_nombr1,
      per_nombr2: c.persona.per_nombr2,
      per_apell1: c.persona.per_apell1,
      per_apell2: c.persona.per_apell2,
      per_estciv: c.persona.per_estciv,
      per_fechna: c.persona.per_fechna,
      cli_tpcid: c.tipo_cliente?.tpc_id ?? null,
      cli_ruc: c.cli_ruc,
      cli_estado: c.cli_estado,
      contactos: c.contactos?.length
        ? c.contactos.map(x => ({ con_tipo: x.con_tipo, con_valor: x.con_valor }))
        : [{ con_tipo: 'CELULAR', con_valor: '' }],
    };
  }

  // --- Contactos ---

  agregarContacto(): void {
    this.form.contactos.push({ con_tipo: 'CELULAR', con_valor: '' });
  }

  quitarContacto(i: number): void {
    if (this.form.contactos.length === 1) {
      this.toastr.warning('Debe mantener al menos un contacto');
      return;
    }
    this.form.contactos.splice(i, 1);
    this.limpiarErroresContacto();
  }

  /** Los índices se corren al borrar una fila; se limpian para no mostrar errores desfasados */
  private limpiarErroresContacto(): void {
    Object.keys(this.errors)
      .filter(k => k.startsWith('contactos'))
      .forEach(k => delete this.errors[k]);
  }

  errorContacto(i: number, campo: string): string | null {
    return this.errors[`contactos.${i}.${campo}`]?.[0] ?? null;
  }

  get errorContactosGeneral(): string | null {
    return this.errors['contactos']?.[0] ?? null;
  }

  // --- Flujo híbrido ---

  buscarPersona(): void {
    if (this.isEdit || !this.form.per_tpdid || !this.form.per_cedula) { return; }

    this.buscando = true;
    this.clienteService.buscarPersona(this.form.per_tpdid, this.form.per_cedula).subscribe({
      next: (resp) => {
        this.buscando = false;

        if (!resp.existe) { this.personaEncontrada = false; return; }

        if (resp.es_cliente) {
          this.errors = { per_cedula: ['Esta persona ya está registrada como cliente.'] };
          this.personaEncontrada = false;
          return;
        }

        const p = resp.persona!;
        this.form = {
          ...this.form,
          per_id: p.per_id,
          per_nombr1: p.per_nombr1, per_nombr2: p.per_nombr2,
          per_apell1: p.per_apell1, per_apell2: p.per_apell2,
          per_estciv: p.per_estciv, per_fechna: p.per_fechna,
          contactos: resp.contactos?.length
            ? resp.contactos.map(c => ({ con_tipo: c.con_tipo, con_valor: c.con_valor }))
            : this.form.contactos,
        };
        this.personaEncontrada = true;
        this.toastr.info('La persona ya existe en el sistema. Se completaron sus datos.');
      },
      error: () => { this.buscando = false; },
    });
  }

  /** Solo sugiere si el documento seleccionado es Cédula de Identidad */
  sugerirRuc(): void {
    const tipo = this.config.tipos_documento.find(t => t.tpd_id === this.form.per_tpdid);
    if (tipo?.tpd_codigo === 'CI' && !this.form.cli_ruc && this.form.per_cedula) {
      this.form.cli_ruc = this.form.per_cedula + '-';
    }
  }

  // --- Guardado ---

  guardar(): void {
    if (this.submitting) { return; }
    this.submitting = true;
    this.errors = {};

    const req$ = this.isEdit
      ? this.clienteService.update(this.cliente!.cli_id, this.form)
      : this.clienteService.store(this.form);

    req$.subscribe({
      next: (resp) => {
        this.submitting = false;
        this.toastr.success(this.isEdit
          ? 'Cliente actualizado correctamente'
          : 'Cliente registrado correctamente');
        this.guardado.emit({ data: resp.data, isEdit: this.isEdit });
        this.cerrar();
      },
      error: (err) => this.handleError(err),
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  private handleError(err: any): void {
    this.submitting = false;
    if (err.status === 422) {
      this.errors = err.error.errors;
      this.toastr.warning('Revise los campos marcados en rojo', 'Validación');
      return;
    }
    this.toastr.error(err.error?.message ?? 'Ocurrió un error inesperado');
  }
}