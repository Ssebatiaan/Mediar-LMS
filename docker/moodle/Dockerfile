# Imagen de Moodle para Mediar: erseco/alpine-moodle + datos ICU completos.
#
# Alpine trae por defecto solo los datos ICU de inglés (icu-data-en), así que
# PHP intl formatea las fechas en inglés ("7 de March de 2027") aunque Moodle
# esté en español. icu-data-full agrega todos los idiomas (es_CO incluido).
FROM erseco/alpine-moodle:5.3.1

USER root
RUN apk add --no-cache icu-data-full
USER nobody
