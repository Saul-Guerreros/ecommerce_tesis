import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ChequeDeclarado, DatosCheque, TipoCheque } from '../../../core/models/checkout.model';

type CampoCheque = 'banco' | 'nrodoc' | 'nombre' | 'nro' | 'fecha_cobro';

export function chequeVacio(): ChequeDeclarado {
  return { tipo: null, banco: '', nrodoc: '', nombre: '', nro: '', fecha_cobro: '' };
}

/**
 * Un cheque está completo cuando tiene tipo, los datos del titular y el
 * número del papel. La fecha de cobro se suma sólo en el diferido: en un
 * cheque a la vista no existe.
 */
export function chequeCompleto(cheque: ChequeDeclarado | null | undefined): boolean {
  if (!cheque || !cheque.tipo) return false;

  const basicos = cheque.banco.trim() !== ''
    && cheque.nrodoc.trim() !== ''
    && cheque.nombre.trim() !== ''
    && cheque.nro.trim() !== '';

  if (!basicos) return false;

  return cheque.tipo !== 'DIFERIDO' || cheque.fecha_cobro.trim() !== '';
}

/**
 * Datos que el cliente declara sobre el cheque que va a entregar.
 *
 * Es presentacional: no llama a ningún servicio. Recibe el cheque actual y
 * emite uno nuevo en cada cambio (sin mutar el recibido), así el
 * contenedor decide dónde guardarlo. Lo usan el pago con un solo método
 * (paso-pago) y cada bloque del pago dividido (pago-dividido).
 */
@Component({
  selector: 'app-cheque-form',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cheque-form.component.html',
  styleUrl: './cheque-form.component.css',
})
export class ChequeFormComponent {
  @Input() datos!: DatosCheque;
  @Input() cheque: ChequeDeclarado = chequeVacio();

  /**
   * Con un solo método la descripción va dentro del formulario. En el
   * pago dividido la muestra la tarjeta "Datos del Método de Pago", así
   * que acá se omite.
   */
  @Input() mostrarDescripcion = false;

  /** El contenedor lo pone en true cuando el cliente intenta enviar. */
  @Input() intentoContinuar = false;

  /** Prefijo de los id de los inputs: con varios bloques no pueden repetirse. */
  @Input() idBase = 'chq';

  @Output() chequeChange = new EventEmitter<ChequeDeclarado>();

  /** Un campo recién se marca como faltante después de pasar por él. */
  tocados: Record<CampoCheque, boolean> = {
    banco: false, nrodoc: false, nombre: false, nro: false, fecha_cobro: false,
  };

  /**
   * Cambiar a "a la vista" limpia la fecha de cobro: si quedara cargada, el
   * cliente creería haber fijado una fecha de depósito que el sistema
   * después descarta.
   */
  elegirTipo(tipo: TipoCheque): void {
    const nuevo: ChequeDeclarado = { ...this.cheque, tipo };

    if (tipo !== 'DIFERIDO') {
      nuevo.fecha_cobro = '';
      this.tocados.fecha_cobro = false;
    }

    this.chequeChange.emit(nuevo);
  }

  onTexto(campo: CampoCheque, evento: Event): void {
    const valor = (evento.target as HTMLInputElement).value;
    const nuevo: ChequeDeclarado = { ...this.cheque };
    nuevo[campo] = valor;

    this.chequeChange.emit(nuevo);
  }

  tocar(campo: CampoCheque): void {
    this.tocados[campo] = true;
  }

  errorEn(campo: CampoCheque): boolean {
    return (this.intentoContinuar || this.tocados[campo])
      && (this.cheque[campo] ?? '').trim() === '';
  }

  get tipoInvalido(): boolean {
    return this.intentoContinuar && !this.cheque.tipo;
  }

  get esDiferido(): boolean {
    return this.cheque.tipo === 'DIFERIDO';
  }

  /** El banco no acepta un cheque con fecha de cobro ya pasada. */
  get fechaMinima(): string {
    return new Date().toISOString().slice(0, 10);
  }
}