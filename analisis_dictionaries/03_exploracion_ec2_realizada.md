# Exploracion realizada en EC2 (julio 2026)

## Que habia antes del diccionario UA

- Extensiones en `/opt/libreoffice25.8/share/extensions/`: `dict-en`, `dict-es`, `dict-fr`.
- **Sin** diccionario personalizado UA.
- Perfil `.libreoffice-uno/user/` sin `wordbook` con palabras custom.
- `ALLOWLIST` / `KNOWN_OK` en Python vacios (y no se usaran).

## Prueba base del SpellChecker (antes de UA)

| Palabra | isValid |
|---------|---------|
| hola | True |
| Introduccion | False |
| Introducción | True |
| Universidad | True |

Conclusion: motor es-GT operativo.

## Hallazgo clave

Copiar archivos a `share/extensions/dict-ua-med/` **no registra** el diccionario en LibreOffice. Hace falta **`unopkg add --shared`** con un `.oxt`.

## Instalacion exitosa (ultima ejecucion)

```
unopkg done.
Identifier: org.ua.dictionaries.med-gt
```

Quedo en cache UNO packages, no solo en carpeta manual.

## Carpeta manual residual (opcional eliminar)

```
/opt/libreoffice25.8/share/extensions/dict-ua-med/
```

Instalacion vieja por copia directa. Puede confundir; la activa es la de `unopkg`:

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-med
```
