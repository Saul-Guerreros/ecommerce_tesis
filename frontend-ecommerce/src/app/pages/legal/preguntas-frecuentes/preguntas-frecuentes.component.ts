import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FaqPublicoService, FaqGrupo } from '../../../core/services/faq-publico.service';

@Component({
  selector: 'app-preguntas-frecuentes',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './preguntas-frecuentes.component.html',
})
export class PreguntasFrecuentesComponent implements OnInit {
  grupos: FaqGrupo[] = [];
  cargando = true;

  constructor(private service: FaqPublicoService) {}

  ngOnInit(): void {
    this.service.obtener().subscribe({
      next: (grupos) => { this.grupos = grupos; this.cargando = false; },
      error: () => (this.cargando = false),
    });
  }
}