# PHP executant R portable a DreamHost

Prova mínima perquè PHP executi GNU R en un servidor compartit de DreamHost **sense `sudo` i sense instal·lar paquets al sistema**. El runtime és un paquet precompilat per a Linux x86_64 i s'executa sota demanda; no inicia cap servei persistent.

## Per què no fem servir Rserve?

Rserve és un dimoni persistent. DreamHost indica que els processos persistents no estan permesos al Shared Hosting. Per a aquesta prova, PHP crea un procés R curt per cada petició i el tanca immediatament. Això comprova el requisit essencial (PHP → R) sense dependre d'un port, un servei o privilegis d'administració.

## Obtenir el paquet complet

El runtime és massa gran per desar-lo directament al repositori. GitHub Actions el genera amb binaris precompilats de conda-forge:

1. Obre la pestanya **Actions** del repositori.
2. Obre el darrer procés **Build portable R for DreamHost** que hagi acabat en verd.
3. A **Artifacts**, descarrega `rserve-test-dreamhost-linux-x86_64`.
4. Dins del ZIP trobaràs `rserve-test-dreamhost-linux-x86_64.tar.gz`.

L'artefacte de GitHub caduca al cap de 30 dies. Si ha caducat, executa el workflow manualment amb **Run workflow**.

## Pujar-ho a DreamHost

Puja el `.tar.gz` al directori del domini i extreu-lo allà. Amb SSH seria, per exemple:

```sh
cd ~/example.com
tar -xzf rserve-test-dreamhost-linux-x86_64.tar.gz
```

Això només copia/descomprimeix fitxers al teu espai d'usuari; no instal·la res al sistema ni necessita `sudo`.

Visita després:

- `https://example.com/index.php` — executa `r/hello.R` i mostra el resultat.
- `https://example.com/diagnostics.php` — mostra comprovacions útils si falla.

La primera petició pot trigar una mica més: el codi reubica automàticament el runtime a la ruta real del domini i crea `runtime/.relocated`. Les peticions següents ja no ho tornen a fer.

Resultat esperat:

```text
PHP ha executat R correctament.

Hola des de R!
Versió: R version 4.4.x (...)
Mitjana de 2, 4, 6 i 8: 5.0
```

## Requisits i límits

- Servidor Linux **x86_64**.
- PHP amb `proc_open()` habilitat.
- Uns quants centenars de MB lliures per al runtime descomprimit.
- El directori `runtime/` ha de ser escrivible durant la primera execució.
- Aquesta prova és per a càlculs curts. Shared Hosting té límits de CPU, memòria i durada de les peticions.

DreamHost documenta que els seus servidors són Ubuntu i que `exec`/`shell_exec` estan disponibles, però la configuració concreta del domini pot variar. `diagnostics.php` permet comprovar el cas real.

## Estructura

```text
index.php                  entrada de prova
diagnostics.php            diagnòstic del servidor
r/hello.R                  script R mínim
src/PortableR.php          execució, timeout i reubicació inicial
.htaccess                  impedeix accés web directe al runtime i al codi font
.github/workflows/...      construeix i valida el paquet portable
runtime/                   només existeix dins de l'artefacte generat
```

## Seguretat

No passis mai text rebut d'un usuari directament a R. Aquest exemple només executa un fitxer R fix del servidor, sense arguments externs. El `.htaccess` bloqueja l'accés HTTP directe a `runtime/`, `r/` i `src/`.

## Fonts

- [DreamHost: tecnologies compatibles](https://help.dreamhost.com/hc/en-us/articles/217141627-Supported-and-unsupported-technologies)
- [DreamHost: processos persistents](https://help.dreamhost.com/hc/en-us/articles/214869648-Persistent-processes)
- [DreamHost: accés root/sudo](https://help.dreamhost.com/hc/en-us/articles/216994417-Is-root-sudo-access-available)
- [conda-pack](https://conda.github.io/conda-pack/)
