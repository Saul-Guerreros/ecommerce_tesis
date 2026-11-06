/**
 * Comprime una foto en el navegador antes de subirla: la reduce a un lado mayor
 * máximo (1600 px por defecto) y la guarda como JPEG. Una foto de celular de
 * 6-10 MB queda en unos cientos de KB, con detalle de sobra para el técnico.
 * Al redibujarla en un canvas se pierden los metadatos EXIF (incluida la ubicación GPS).
 *
 * Solo usar desde eventos del navegador (usa document, Image y canvas); no en el servidor (SSR).
 */
export interface OpcionesCompresion {
  /** Lado mayor máximo, en px. */
  maxLado?: number;
  /** Calidad JPEG inicial (0 a 1). */
  calidad?: number;
  /** Tope de tamaño del resultado, en bytes. */
  maxBytes?: number;
  /** Por debajo de este tamaño la foto se deja tal cual. */
  umbralBytes?: number;
}

const TIPOS_ACEPTADOS = /^image\/(jpeg|png|webp)$/;

function cargarImagen(archivo: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(archivo);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo leer la imagen')); };
    img.src = url;
  });
}

function aBlobJpeg(canvas: HTMLCanvasElement, calidad: number): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', calidad));
}

export async function comprimirImagen(archivo: File, opciones: OpcionesCompresion = {}): Promise<File> {
  const {
    maxLado = 1600,
    calidad = 0.8,
    maxBytes = 4.5 * 1024 * 1024,
    umbralBytes = 1024 * 1024,
  } = opciones;

  // Ya es liviana: se deja tal cual para no recomprimir ni perder calidad.
  if (archivo.size <= umbralBytes && TIPOS_ACEPTADOS.test(archivo.type)) return archivo;

  const img = await cargarImagen(archivo);
  let lado = maxLado;
  let q = calidad;
  let blob: Blob | null = null;

  // Hasta 4 intentos: si todavía pesa de más, baja la calidad y el tamaño.
  for (let intento = 0; intento < 4; intento++) {
    const escala = Math.min(1, lado / Math.max(img.naturalWidth, img.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(img.naturalWidth * escala));
    canvas.height = Math.max(1, Math.round(img.naturalHeight * escala));

    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('El navegador no permite procesar imágenes');
    ctx.fillStyle = '#fff';   // PNG/WebP con transparencia: fondo blanco en vez de negro
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    blob = await aBlobJpeg(canvas, q);
    if (blob && blob.size <= maxBytes) break;

    q = Math.max(0.5, q - 0.1);
    lado = Math.round(lado * 0.8);
  }

  if (!blob) throw new Error('No se pudo comprimir la imagen');

  // Si recomprimir no ayudó y el original ya cumple, se conserva el original.
  if (blob.size >= archivo.size && archivo.size <= maxBytes && TIPOS_ACEPTADOS.test(archivo.type)) return archivo;

  const nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';
  return new File([blob], nombre, { type: 'image/jpeg', lastModified: Date.now() });
}