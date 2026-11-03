# Argos

Lee primero `README.md`: explica qué hace Argos, cómo levantarlo, los comandos y las decisiones del proyecto.

## Reglas para trabajar en Argos

- Confirma con el usuario antes de cambiar código y explica el porqué del cambio.
- No hagas commits ni push: los hace el usuario. Dale los comandos.
- Credenciales y secretos solo en `.env`, nunca en el código ni en la config versionada.
- No accedas a la base de datos de Cuponidad. Los repos de Cuponidad (`~/proyectos/cuponidad`) son solo de lectura: no se modifican ni se ejecutan. Toda solución va del lado de Argos.
- Los comandos van por Sail (`./vendor/bin/sail ...` o el `Makefile`), no con el PHP del host.
- Después de cambiar la lógica de cálculo, corre `make clear` antes de verificar en el panel; después de cambiar el front, `make build`.
- No agregues comentarios propios al código. Se mantienen los de Laravel y las anotaciones de tipo (`@return`, `@var`).
- Nombres de clases, métodos y variables en español, como el resto del proyecto.
