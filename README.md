## Para hacer funcionar la pagina localmente, necesitas descargar e instalar XAMPP, utilzando el siguente link: https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe
Despues de instalarlo, debes activar las opciones de Apache y MySQL

Para descargar la carpeta zip de click en el boton verde 'code / codigo', despues de click a donde  dice 'download zip / descargar zip'

Dentro de la carpeta zip se encuentra una carpeta llamada inventariomeca, en ella estan todos los archivos necesarios para hacer funcionar la pagina más el script SQL para la base de datos.

Ya que tengas todo descargado, dirigete hacia C:\xampp\htdocs, dentro de la carpeta htdocs vas a dejar la carpeta inventariomeca anteriormente descargada.

Para la base de datos debe de entrar a su navegador y dirigirse a http://localhost/phpmyadmin/index.php?route=/server/databases, donde le apareceran en la pantalla las bases de datos del sistema, en el apartado 'create database' debe llenar el espacio 'database name' con el nombre 'inventariomeca', 

<img width="524" height="168" alt="image" src="https://github.com/user-attachments/assets/73f3ef2a-07d3-4d2d-8b82-98a436d3068a" />

Despues de crear la base de datos se abrira esta misma y en el menu de la parte superior debe seleccionar el boton que dice import:

<img width="490" height="56" alt="image" src="https://github.com/user-attachments/assets/1f57c803-570e-4575-ad07-e17405b94f2d" />

Ya en el menu de import debe dar click al boton 'Choose file' y buscar el archivo llamado 'inventariomeca.sql' dentro de la carpeta inventariomeca y abrir ese archivo.
<img width="1122" height="286" alt="image" src="https://github.com/user-attachments/assets/c3307155-8395-4971-a313-a83800a9bfcc" />

No es necesario modificar ninguna configuración, solo navege a la parte inferior de la pagina y de click al boton 'Import'.
Y listo la base de datos ya esta funcional.

## Para asegurarse de que todo funciono perfectamente, dirijase a localhost/inventariomeca/login.php para iniciar sesión y acceder a la pagina.
