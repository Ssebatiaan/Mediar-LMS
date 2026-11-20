"""Genera las imágenes de tarjeta de curso por área Saber 11 (identidad Mediar).

Uso (en el host, desde la raíz del proyecto):
    python scripts/marca/generar_imagenes_cursos.py

Salida: assets/marca/cursos/<area>.png (800x400) y assets/marca/cursos/vista-previa.png
Las asigna a cada curso scripts/setup_tema.php según el campo de curso "Área Saber 11".

Diseño: fondo gris oscuro de marca con un degradado suave, un círculo naranja que
sale del borde (eco de la gota del logo) y símbolos de la asignatura. Fuentes de
Windows (Segoe UI / Segoe UI Symbol): solo se usan para dibujar la imagen.
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

RAIZ = Path(__file__).resolve().parents[2]
SALIDA = RAIZ / "assets" / "marca" / "cursos"
FUENTES = Path("C:/Windows/Fonts")

ANCHO, ALTO = 800, 400
NARANJA = (255, 93, 28)
GRIS_OSCURO = (37, 37, 37)
GRIS_MEDIO = (58, 58, 58)

# Símbolo principal y símbolos secundarios por área.
AREAS = {
    "matematicas": ("π", ["∑", "√", "%", "÷", "∞", "x²"]),
    "lectura": ("Aa", ["¶", "“", "”", "?", "¿", "…"]),
    "sociales": ("⚖", ["§", "★", "✉", "⚑", "☖", "✎"]),
    "naturales": ("⚛", ["⚗", "H₂O", "∿", "☀", "✿", "⚡"]),
    "ingles": ("EN", ["ABC", "Hi!", "?", "A1", "B1", "✎"]),
    "simulacro": ("500", ["✓", "✓", "A", "B", "C", "D"]),
    "banco": ("?", ["A", "B", "C", "D", "✎", "✓"]),
}


def fuente(nombre, tam):
    return ImageFont.truetype(str(FUENTES / nombre), tam)


def fuente_para(texto, tam, negrita=True):
    """Segoe UI para letras y números; Segoe UI Symbol para pictogramas."""
    if all(ord(c) < 0x2000 or c in "“”…₂²" for c in texto):
        return fuente("seguibl.ttf" if negrita else "seguisb.ttf", tam)
    return fuente("seguisym.ttf", tam)


def fondo():
    """Degradado diagonal de gris oscuro a gris medio."""
    im = Image.new("RGB", (ANCHO, ALTO), GRIS_OSCURO)
    px = im.load()
    for y in range(ALTO):
        for x in range(ANCHO):
            t = (x / ANCHO * 0.6 + y / ALTO * 0.4)
            px[x, y] = tuple(round(GRIS_OSCURO[i] + (GRIS_MEDIO[i] - GRIS_OSCURO[i]) * t) for i in range(3))
    return im


def generar(area, principal, secundarios, indice):
    im = fondo().convert("RGBA")

    # Resplandor naranja difuso detrás del círculo.
    brillo = Image.new("RGBA", im.size, (0, 0, 0, 0))
    ImageDraw.Draw(brillo).ellipse((360, -40, 840, 440), fill=(*NARANJA, 90))
    im = Image.alpha_composite(im, brillo.filter(ImageFilter.GaussianBlur(60)))

    capa = Image.new("RGBA", im.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(capa)
    # Círculo naranja saliendo por la derecha y anillo fino.
    d.ellipse((450, 50, 790, 390), fill=(*NARANJA, 255))
    d.ellipse((400, 0, 840, 440), outline=(*NARANJA, 110), width=4)

    # Símbolos secundarios dispersos (posición fija por área para que sea reproducible).
    posiciones = [(60, 50), (220, 120), (100, 230), (290, 285), (330, 40), (40, 320)]
    desplazamiento = indice * 37
    for n, (simbolo, (x, y)) in enumerate(zip(secundarios, posiciones)):
        tam = 46 + ((n * 13 + desplazamiento) % 30)
        alfa = 70 + ((n * 29 + desplazamiento) % 60)
        d.text((x, y), simbolo, font=fuente_para(simbolo, tam, negrita=False), fill=(255, 255, 255, alfa))

    # Símbolo principal en blanco, centrado sobre el círculo.
    tam = 150 if len(principal) <= 2 else 112
    f = fuente_para(principal, tam)
    caja = d.textbbox((0, 0), principal, font=f)
    cx, cy = 620, 220
    d.text((cx - (caja[0] + caja[2]) / 2, cy - (caja[1] + caja[3]) / 2), principal, font=f, fill=(255, 255, 255, 255))

    im = Image.alpha_composite(im, capa).convert("RGB")
    im.save(SALIDA / f"{area}.png", optimize=True)
    return im


def main():
    SALIDA.mkdir(parents=True, exist_ok=True)
    imagenes = [generar(a, p, s, i) for i, (a, (p, s)) in enumerate(AREAS.items())]

    # Vista previa en cuadrícula 3x2 (para revisar).
    mini = [im.resize((400, 200)) for im in imagenes]
    vista = Image.new("RGB", (400 * 3 + 40, 200 * 3 + 40), (245, 245, 245))
    for n, im in enumerate(mini):
        vista.paste(im, (10 + (n % 3) * 410, 10 + (n // 3) * 210))
    vista.save(SALIDA / "vista-previa.png")
    print(f"{len(imagenes)} imágenes -> {SALIDA}")


if __name__ == "__main__":
    main()
