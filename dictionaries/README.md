# Diccionarios UA por facultad / uso (es-GT)

Complementan `es_GT` en LibreOffice (servicio `spellers-main`).  
**Ortografia prioritaria** en todos excepto `ang` (anglicismos intencionales).

| Codigo | Carpeta | Id extension | Version | Lemas (aprox.) | Uso |
|--------|---------|--------------|---------|----------------|-----|
| med | `dict-ua-med` | `org.ua.dictionaries.med-gt` | 2.4.0 | ~147937 | Medicina |
| odo | `dict-ua-odo` | `org.ua.dictionaries.odo-gt` | 1.2.0 | ~56457 | Odontologia |
| der | `dict-ua-der` | `org.ua.dictionaries.der-gt` | 1.4.0 | ~70137 | Derecho |
| eco | `dict-ua-eco` | `org.ua.dictionaries.eco-gt` | 1.3.0 | ~50376 | Ciencias Economicas |
| arq | `dict-ua-arq` | `org.ua.dictionaries.arq-gt` | 1.3.0 | ~58024 | Arquitectura |
| pol | `dict-ua-pol` | `org.ua.dictionaries.pol-gt` | 1.3.0 | ~51554 | Estudios Politicos / RR.II. |
| psi | `dict-ua-psi` | `org.ua.dictionaries.psi-gt` | 1.3.0 | ~50269 | Psicologia |
| uni | `dict-ua-uni` | `org.ua.dictionaries.uni-gt` | 1.3.0 | ~44960 | Terminos universitarios (ES) |
| ang | `dict-ua-ang` | `org.ua.dictionaries.ang-gt` | 1.3.0 | ~20473 | Anglicismos universitarios comunes |

Guia operativa: `analisis_dictionaries/11_guia_crear_y_actualizar_diccionarios_ua.md`  
Referencias: `analisis_dictionaries/10_referencias_diccionarios_por_facultad.md`

## Instalar todos (EC2)

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries
sed -i 's/\r$//' install_all_ua_dicts.sh
sudo bash install_all_ua_dicts.sh
```

## Notas

- Wave 2 (`gen_wave2_expand_all.py`): morph + harvest + merge en `expanded_*.txt`.
- XML declaration = `<?xml version="1.0"?>`; version del paquete en `<version value="..."/>`.
- Install scripts usan `python3` del sistema para prep UTF-8.
- Semillas FP syllabus: `source/fp_seeds.txt` por area.
