# Notas privadas del administrador - IDS
# NO DISTRIBUIR

- El endpoint /network.php acepta comandos ping/traceroute para operadores.
  Aun no tiene validacion, pero "en teoria" nadie externo deberia usarlo.
- El endpoint /download.php lee archivos del bucket pero no filtra ".." (TODO).
- Las credenciales internas estan en /home/admin/.env dentro de db_ssh.
- Los respaldos antiguos estan en ../config/app.ini (fuera del bucket).

Bandera de proximidad: FLAG{UCC_IDS_LFI_Found}
