"""Genera los logos de Mediar con fondo transparente a partir del JPEG original.

Uso (en el host, desde la raíz del proyecto):
    python scripts/marca/generar_logos.py

Entrada:  assets/logo-mediar.jpeg (fondo blanco, 240x240)
Salida:   assets/marca/logo-mediar.png         logo completo (gota + MEDIAR + lema)
          assets/marca/logo-mediar-icono.png   solo la gota (barra de navegación)
          assets/marca/vista-previa.png        ambos sobre fondo claro y oscuro (para revisar)

Cómo funciona:
- El logo es naranja de marca sobre blanco. Para cada píxel se estima cuánto naranja
  tiene (canal azul: el naranja #FF5D1C casi no tiene azul y el blanco sí) y se
  convierte en naranja puro con esa opacidad: así los bordes quedan suavizados.
- El blanco DENTRO de la gota es parte del dibujo y se conserva opaco. Se detecta
  porque no está conectado con el borde de la imagen (relleno por inundación).
- Los huecos blancos de las letras (A, R, D...) sí se vuelven transparentes.

NOTA: es una solución para la demo. Para producción conviene el logo original en vector (SVG).
"""
from collections import deque
from pathlib import Path

from PIL import Image

RAIZ = Path(__file__).resolve().parents[2]
ENTRADA = RAIZ / "assets" / "logo-mediar.jpeg"
SALIDA = RAIZ / "assets" / "marca"

NARANJA = (255, 93, 28)  # #FF5D1C
ESCALA = 2               # se duplica la resolución para pantallas de alta densidad
FIN_ICONO = 0.78         # fracción de la altura donde termina la gota (antes del texto)
RUIDO = 0.12             # opacidad por debajo de la cual un píxel se considera ruido del JPEG


def alfa_naranja(pixel):
    """Opacidad estimada del naranja sobre blanco, a partir del canal azul."""
    azul = pixel[2]
    a = (255 - azul) / (255 - NARANJA[2])
    # Se descarta el ruido del JPEG (opacidades muy bajas) y se reescala el resto.
    a = (a - RUIDO) / (1 - RUIDO)
    return max(0.0, min(1.0, a))


def exterior(im, umbral=200):
    """Píxeles claros conectados con el borde (el fondo, no el interior de la gota)."""
    w, h = im.size
    px = im.load()
    visto = [[False] * w for _ in range(h)]
    cola = deque()
    for x in range(w):
        cola.extend([(x, 0), (x, h - 1)])
    for y in range(h):
        cola.extend([(0, y), (w - 1, y)])
    while cola:
        x, y = cola.popleft()
        if not (0 <= x < w and 0 <= y < h) or visto[y][x]:
            continue
        if px[x, y][2] < umbral:  # poco azul = naranja, es borde del dibujo
            continue
        visto[y][x] = True
        cola.extend([(x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)])
    return visto


def main():
    original = Image.open(ENTRADA).convert("RGB")
    im = original.resize((original.width * ESCALA, original.height * ESCALA), Image.LANCZOS)
    w, h = im.size
    px = im.load()
    fuera = exterior(im)
    limite_icono = int(h * FIN_ICONO)

    salida = Image.new("RGBA", (w, h))
    po = salida.load()
    for y in range(h):
        for x in range(w):
            p = px[x, y]
            if y < limite_icono and not fuera[y][x]:
                po[x, y] = (*p, 255)  # interior de la gota: se conserva tal cual
            else:
                po[x, y] = (*NARANJA, round(alfa_naranja(p) * 255))

    SALIDA.mkdir(parents=True, exist_ok=True)
    completo = salida.crop(salida.getbbox())
    completo.save(SALIDA / "logo-mediar.png")

    icono = salida.crop((0, 0, w, limite_icono))
    icono = icono.crop(icono.getbbox())
    icono.save(SALIDA / "logo-mediar-icono.png")

    # Vista previa sobre fondo claro (#F5F5F5) y oscuro (#252525).
    margen = 40
    ancho = completo.width + icono.width + margen * 3
    alto = max(completo.height, icono.height) + margen * 2
    vista = Image.new("RGB", (ancho, alto * 2), (245, 245, 245))
    vista.paste(Image.new("RGB", (ancho, alto), (37, 37, 37)), (0, alto))
    for fila in (0, alto):
        vista.paste(completo, (margen, fila + margen), completo)
        vista.paste(icono, (completo.width + margen * 2, fila + margen), icono)
    vista.save(SALIDA / "vista-previa.png")

    print(f"logo completo {completo.size}, icono {icono.size} -> {SALIDA}")


if __name__ == "__main__":
    main()
