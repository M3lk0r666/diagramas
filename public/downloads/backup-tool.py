#!/usr/bin/env python3
"""
╔══════════════════════════════════════════════════════════════╗
║      MULTI-VENDOR SWITCH BACKUP TOOL - v1.8                ║
║   Soporte: Cisco IOS / IOS-XE  +  Extreme Networks (EXOS)    ║
║   Protocolo: SSH via Netmiko / Scrapli                       ║
║   Alberto Arellano A. / CCNA / CCNP / Automation / IA        ║
╚══════════════════════════════════════════════════════════════╝
Dependencias:
    pip install netmiko
    pip install scrapli           (opcional, mejora rendimiento Cisco)

Notas Multi-Vendor:
  - Cisco  : usa 'terminal length 0' + 'show running-config'
  - Extreme: usa 'disable clipaging' (al inicio) + 'show configuration'
             y al finalizar restaura con 'enable clipaging'.
  - Cada vendor permite agregar comandos adicionales cuya salida se
    anexa al archivo de respaldo (sección "Comandos Personalizados").
"""

import tkinter as tk
from tkinter import ttk, scrolledtext, messagebox, filedialog
import threading
import os
import sys
import datetime
import queue
import re
import socket
import ipaddress
import subprocess
import platform
import time

try:
    from netmiko import ConnectHandler, NetmikoTimeoutException, NetmikoAuthenticationException
    NETMIKO_AVAILABLE = True
except ImportError:
    NETMIKO_AVAILABLE = False

# pyserial es opcional: sólo se requiere para el modo CONSOLA (serial).
try:
    import serial
    import serial.tools.list_ports
    PYSERIAL_AVAILABLE = True
except ImportError:
    PYSERIAL_AVAILABLE = False


# ─────────────────────────────────────────────────────────────
#  UTILIDADES DE CONECTIVIDAD (pre-checks)
# ─────────────────────────────────────────────────────────────
def validate_ip(host: str) -> tuple[bool, str]:
    """
    Valida que el host sea una IP válida o un hostname con formato correcto.
    Retorna (valido, mensaje).
    """
    host = host.strip()
    if not host:
        return False, "Campo de IP vacío."

    # Intentar como dirección IP
    try:
        ipaddress.ip_address(host)
        return True, ""
    except ValueError:
        pass

    # Validar como hostname (letras, números, guiones, puntos)
    hostname_re = re.compile(
        r"^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*"
        r"[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$"
    )
    if hostname_re.match(host):
        return True, ""

    return False, f"'{host}' no es una IP ni hostname válido."


def check_duplicates(hosts: list[str]) -> list[str]:
    """Retorna lista de IPs duplicadas."""
    seen = set()
    dupes = []
    for h in hosts:
        if h in seen:
            dupes.append(h)
        seen.add(h)
    return dupes


def tcp_ping(host: str, port: int = 22, timeout: float = 3.0) -> tuple[bool, str]:
    """
    Verifica si el puerto TCP (default 22/SSH) está abierto en el host.
    Mucho más rápido que esperar el timeout completo de Netmiko.
    Retorna (accesible, mensaje).
    """
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((host, port))
        sock.close()
        if result == 0:
            return True, f"Puerto {port}/TCP accesible."
        else:
            return False, f"Puerto {port}/TCP cerrado o filtrado (código {result})."
    except socket.gaierror:
        return False, f"No se puede resolver el host '{host}'. Verifica DNS o la IP."
    except socket.timeout:
        return False, f"Timeout al verificar puerto {port}/TCP en {host}."
    except OSError as e:
        return False, f"Error de red: {e}"


def icmp_ping(host: str, timeout: int = 2) -> tuple[bool, str]:
    """
    Envía un ping ICMP. Retorna (alcanzable, mensaje).
    Funciona en Windows y Linux/macOS.
    """
    try:
        system = platform.system().lower()
        if system == "windows":
            cmd = ["ping", "-n", "1", "-w", str(timeout * 1000), host]
        else:
            cmd = ["ping", "-c", "1", "-W", str(timeout), host]

        proc = subprocess.run(
            cmd, stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL, timeout=timeout + 2
        )
        if proc.returncode == 0:
            return True, f"ICMP ping OK a {host}."
        else:
            return False, f"Host {host} no responde a ICMP ping."
    except subprocess.TimeoutExpired:
        return False, f"Timeout ICMP ping a {host}."
    except FileNotFoundError:
        return False, "Comando ping no disponible en este sistema."
    except Exception as e:
        return False, f"Error ping: {e}"


def pre_check_host(host: str, ssh_port: int = 22,
                   tcp_timeout: float = 3.0) -> dict:
    """
    Ejecuta todas las verificaciones previas a la conexión SSH:
      1. Validación de formato IP/hostname
      2. Verificación TCP puerto 22
    Retorna dict con: valid, reachable, ssh_open, message, skip
    """
    result = {
        "host"      : host,
        "valid"     : False,
        "reachable" : False,
        "ssh_open"  : False,
        "message"   : "",
        "skip"      : False,   # True = no intentar SSH
    }

    # 1. Validar formato
    valid, msg = validate_ip(host)
    if not valid:
        result["message"] = f"IP/hostname inválido: {msg}"
        result["skip"]    = True
        return result
    result["valid"] = True

    # 2. Verificar puerto TCP 22
    ssh_ok, ssh_msg = tcp_ping(host, port=ssh_port, timeout=tcp_timeout)
    result["ssh_open"] = ssh_ok
    result["reachable"] = ssh_ok

    if not ssh_ok:
        # 2b. Hacer ping ICMP para distinguir "host apagado" vs "SSH bloqueado"
        icmp_ok, icmp_msg = icmp_ping(host, timeout=2)
        if icmp_ok:
            result["message"] = (
                f"Host {host} RESPONDE a ping pero el puerto SSH (22) está "
                f"cerrado o filtrado. Verifica que SSH esté habilitado en el equipo."
            )
        else:
            result["message"] = (
                f"Host {host} NO alcanzable (sin respuesta ICMP ni TCP/22). "
                f"Verifica: IP correcta, cable/VLAN, que el equipo esté encendido."
            )
        result["skip"] = True
        return result

    result["message"] = ssh_msg
    return result


# ─────────────────────────────────────────────────────────────
#  SISTEMA DE SKINS (PALETAS DE COLOR)
# ─────────────────────────────────────────────────────────────
#  Cada skin define el set completo de colores que usa la app.
#  El skin activo se persiste en ~/.multivendor_backup_skin para
#  que al reabrir la herramienta se conserve la preferencia.
#  Para agregar un nuevo skin: añade una entrada al dict SKINS con
#  las MISMAS claves.  Aparecerá automáticamente en el selector.
# ─────────────────────────────────────────────────────────────
SKINS = {
    "GitHub Dark": {
        "BG_DARK"      : "#0d1117",
        "BG_PANEL"     : "#161b22",
        "BG_CARD"      : "#1c2128",
        "BG_INPUT"     : "#21262d",
        "ACCENT_BLUE"  : "#1f6feb",
        "ACCENT_GREEN" : "#3fb950",
        "ACCENT_RED"   : "#f85149",
        "ACCENT_ORANGE": "#d29922",
        "ACCENT_CYAN"  : "#39c5cf",
        "ACCENT_PURPLE": "#a371f7",
        "TEXT_PRIMARY" : "#e6edf3",
        "TEXT_MUTED"   : "#7d8590",
        "TEXT_DIM"     : "#484f58",
        "BORDER"       : "#30363d",
    },
    "Dracula": {
        "BG_DARK"      : "#282a36",
        "BG_PANEL"     : "#21222c",
        "BG_CARD"      : "#343746",
        "BG_INPUT"     : "#44475a",
        "ACCENT_BLUE"  : "#8be9fd",
        "ACCENT_GREEN" : "#50fa7b",
        "ACCENT_RED"   : "#ff5555",
        "ACCENT_ORANGE": "#ffb86c",
        "ACCENT_CYAN"  : "#8be9fd",
        "ACCENT_PURPLE": "#bd93f9",
        "TEXT_PRIMARY" : "#f8f8f2",
        "TEXT_MUTED"   : "#a4a8b8",
        "TEXT_DIM"     : "#6272a4",
        "BORDER"       : "#44475a",
    },
    "Matrix": {
        "BG_DARK"       : "#030807",
        "BG_PANEL"      : "#08110f",
        "BG_CARD"       : "#0d1815",
        "BG_INPUT"      : "#13211d",
        "ACCENT_GREEN"  : "#00ff9c",
        "ACCENT_CYAN"   : "#00e5ff",
        "ACCENT_BLUE"   : "#4f8cff",
        "ACCENT_RED"    : "#ff5c70",
        "ACCENT_ORANGE" : "#ffb347",
        "ACCENT_PURPLE" : "#c084fc",
        "TEXT_PRIMARY"  : "#d7fff0",
        "TEXT_MUTED"    : "#7eb8a1",
        "TEXT_DIM"      : "#44685b",
        "BORDER"        : "#1f3a31",
    },
    "Extreme Elite":{
        "BG_DARK"       : "#0c0b14",
        "BG_PANEL"      : "#141221",
        "BG_CARD"       : "#1d1a2d",
        "BG_INPUT"      : "#27213b",
        "ACCENT_PURPLE" : "#9f6fff",
        "ACCENT_CYAN"   : "#3fe0e8",
        "ACCENT_BLUE"   : "#5b8cff",
        "ACCENT_GREEN"  : "#49d17d",
        "ACCENT_RED"    : "#ff6b81",
        "ACCENT_ORANGE" : "#ffc857",
        "TEXT_PRIMARY"  : "#f5f4ff",
        "TEXT_MUTED"    : "#b7b0d8",
        "TEXT_DIM"      : "#716a8e",
        "BORDER"        : "#3d3556",
    },
    "Extreme Corporativo": {
        "BG_DARK"       : "#0f0b17",
        "BG_PANEL"      : "#181123",
        "BG_CARD"       : "#21182f",
        "BG_INPUT"      : "#2a1f3b",
        "ACCENT_PURPLE" : "#8b5cf6",
        "ACCENT_BLUE"   : "#6366f1",
        "ACCENT_GREEN"  : "#22c55e",
        "ACCENT_RED"    : "#ef4444",
        "ACCENT_ORANGE" : "#f59e0b",
        "ACCENT_CYAN"   : "#22d3ee",
        "TEXT_PRIMARY"  : "#f4f1ff",
        "TEXT_MUTED"    : "#b4accf",
        "TEXT_DIM"      : "#6b6484",
        "BORDER"        : "#3a3150",
    },
    "Cyber Terminal": {
            "BG_DARK"       : "#000000",
            "BG_PANEL"      : "#080808",
            "BG_CARD"       : "#101010",
            "BG_INPUT"      : "#181818",
            "ACCENT_GREEN"  : "#39ff14",
            "ACCENT_CYAN"   : "#00e7ff",
            "ACCENT_BLUE"   : "#4d7cff",
            "ACCENT_RED"    : "#ff4f4f",
            "ACCENT_ORANGE" : "#ffb000",
            "ACCENT_PURPLE" : "#bb86fc",
            "TEXT_PRIMARY"  : "#ffffff",
            "TEXT_MUTED"    : "#b0b0b0",
            "TEXT_DIM"      : "#606060",
            "BORDER"        : "#202020",
    },
}

DEFAULT_SKIN = "GitHub Dark"
_CURRENT_SKIN_NAME = DEFAULT_SKIN  # mutable, lo cambia _apply_skin

# Las globals de color que usa toda la UI se inicializan desde el
# skin activo.  _apply_skin() las reemplaza en sitio.
BG_DARK      = SKINS[_CURRENT_SKIN_NAME]["BG_DARK"]
BG_PANEL     = SKINS[_CURRENT_SKIN_NAME]["BG_PANEL"]
BG_CARD      = SKINS[_CURRENT_SKIN_NAME]["BG_CARD"]
BG_INPUT     = SKINS[_CURRENT_SKIN_NAME]["BG_INPUT"]
ACCENT_BLUE  = SKINS[_CURRENT_SKIN_NAME]["ACCENT_BLUE"]
ACCENT_GREEN = SKINS[_CURRENT_SKIN_NAME]["ACCENT_GREEN"]
ACCENT_RED   = SKINS[_CURRENT_SKIN_NAME]["ACCENT_RED"]
ACCENT_ORANGE= SKINS[_CURRENT_SKIN_NAME]["ACCENT_ORANGE"]
ACCENT_CYAN  = SKINS[_CURRENT_SKIN_NAME]["ACCENT_CYAN"]
ACCENT_PURPLE= SKINS[_CURRENT_SKIN_NAME]["ACCENT_PURPLE"]
TEXT_PRIMARY = SKINS[_CURRENT_SKIN_NAME]["TEXT_PRIMARY"]
TEXT_MUTED   = SKINS[_CURRENT_SKIN_NAME]["TEXT_MUTED"]
TEXT_DIM     = SKINS[_CURRENT_SKIN_NAME]["TEXT_DIM"]
BORDER       = SKINS[_CURRENT_SKIN_NAME]["BORDER"]


def _apply_skin(name: str) -> bool:
    """Reemplaza las globals de color con el skin solicitado.
    Devuelve True si el skin existe y se aplicó."""
    global _CURRENT_SKIN_NAME
    global BG_DARK, BG_PANEL, BG_CARD, BG_INPUT
    global ACCENT_BLUE, ACCENT_GREEN, ACCENT_RED, ACCENT_ORANGE
    global ACCENT_CYAN, ACCENT_PURPLE
    global TEXT_PRIMARY, TEXT_MUTED, TEXT_DIM, BORDER

    if name not in SKINS:
        return False
    s = SKINS[name]
    _CURRENT_SKIN_NAME = name
    BG_DARK       = s["BG_DARK"]
    BG_PANEL      = s["BG_PANEL"]
    BG_CARD       = s["BG_CARD"]
    BG_INPUT      = s["BG_INPUT"]
    ACCENT_BLUE   = s["ACCENT_BLUE"]
    ACCENT_GREEN  = s["ACCENT_GREEN"]
    ACCENT_RED    = s["ACCENT_RED"]
    ACCENT_ORANGE = s["ACCENT_ORANGE"]
    ACCENT_CYAN   = s["ACCENT_CYAN"]
    ACCENT_PURPLE = s["ACCENT_PURPLE"]
    TEXT_PRIMARY  = s["TEXT_PRIMARY"]
    TEXT_MUTED    = s["TEXT_MUTED"]
    TEXT_DIM      = s["TEXT_DIM"]
    BORDER        = s["BORDER"]
    return True


def _skin_config_path() -> str:
    """Ruta del archivo donde se guarda la preferencia de skin."""
    return os.path.join(os.path.expanduser("~"), ".multivendor_backup_skin")


def _load_skin_pref() -> str:
    """Carga la preferencia y aplica el skin.  Devuelve el nombre activo."""
    try:
        with open(_skin_config_path(), "r", encoding="utf-8") as f:
            name = f.read().strip()
        if name in SKINS:
            _apply_skin(name)
            return name
    except Exception:
        pass
    return DEFAULT_SKIN


def _save_skin_pref(name: str) -> None:
    """Persiste la preferencia para próximas ejecuciones."""
    try:
        with open(_skin_config_path(), "w", encoding="utf-8") as f:
            f.write(name)
    except Exception:
        pass


FONT_TITLE   = ("Consolas", 18, "bold")
FONT_HEADING = ("Consolas", 11, "bold")
FONT_LABEL   = ("Consolas", 9)
FONT_MONO    = ("Consolas", 9)
FONT_BTN     = ("Consolas", 10, "bold")
FONT_SMALL   = ("Consolas", 8)

ENABLE_SNMP = False

# ─────────────────────────────────────────────────────────────
#  PERFILES MULTI-VENDOR
# ─────────────────────────────────────────────────────────────
#  Cada vendor define:
#    device_type     -> string Netmiko
#    scrapli_platform-> plataforma Scrapli (None = no usar Scrapli)
#    init_commands   -> comandos previos (ej. desactivar paginación)
#    backup_commands -> comandos cuya salida es el "respaldo principal"
#    cleanup_commands-> comandos finales (ej. restaurar paginación)
#    extra_commands_default -> comandos adicionales de ejemplo
#    color           -> color del badge en la UI
# ─────────────────────────────────────────────────────────────
VENDORS = {
    "Cisco": {
        "device_type"           : "cisco_ios",
        "scrapli_platform"      : "cisco_iosxe",
        "init_commands"         : ["terminal length 0"],
        "backup_commands"       : ["show running-config"],
        "cleanup_commands"      : [],
        "extra_commands_default": (
            "show version\n"
            "show inventory\n"
            "show interfaces status\n"
            "show vlan brief\n"
            "show cdp neighbors detail\n"
            "show ip interface brief\n"
            "show mac address-table\n"
        ),
        "color"                 : ACCENT_BLUE,
    },
    "Extreme": {
        "device_type"           : "extreme_exos",
        "scrapli_platform"      : None,           # Scrapli no soporta EXOS
        # NOTA: en EXOS, "disable clipaging" desactiva la paginación
        # (sale todo de corrido). "enable clipaging" la restaura al
        # default.  Esto es lo que pediste: obtener TODA la información
        # sin intervención del usuario y luego dejar el equipo como estaba.
        "init_commands"         : ["disable clipaging"],
        "backup_commands"       : ["show configuration"],
        "cleanup_commands"      : ["enable clipaging"],
        "extra_commands_default": (
            "show switch detail\n"
            "show version detail\n"
            "show licenses\n"
            "show vlan\n"
            "show iproute\n"
            "show edp ports all\n"
            "show lldp neighbors\n"
            "show fdb\n"
            "show ports no-refresh\n"
        ),
        "color"                 : ACCENT_PURPLE,
    },
}

# Comandos extra introducidos por el usuario en la UI (uno por vendor).
# Se rellenan en CiscoBackupApp._build_commands_panel y se leen al
# disparar el respaldo.
USER_EXTRA_COMMANDS = {v: "" for v in VENDORS}


# ─────────────────────────────────────────────────────────────
#  LÓGICA DE RESPALDO SSH (MULTI-VENDOR)
# ─────────────────────────────────────────────────────────────
def _split_commands(text: str) -> list:
    """Convierte un bloque de texto multi-línea en lista de comandos limpios."""
    if not text:
        return []
    out = []
    for line in text.splitlines():
        c = line.strip()
        if c and not c.startswith("#"):
            out.append(c)
    return out


def _build_section_header(title: str) -> str:
    """Cabecera ASCII para separar secciones dentro del archivo de respaldo."""
    bar = "=" * 70
    return f"\n\n{bar}\n!  {title}\n{bar}\n"


def backup_device_cisco(host, username, password, secret, timeout,
                        extra_commands, vendor_profile, log,
                        cancel_event=None):
    """
    Respaldo Cisco — mantiene la lógica original Scrapli + Netmiko fallback.
    Devuelve (config_text, success, error_message).
    """
    config = None
    is_legacy = False
    backup_cmd = vendor_profile["backup_commands"][0]

    # =========================================================
    # 🔵 SCRAPLI (INTENTO PRINCIPAL)
    # =========================================================
    try:
        from scrapli import Scrapli

        log("Intentando con SCRAPLI...", "info")

        conn = Scrapli(
            host=host,
            auth_username=username,
            auth_password=password,
            auth_strict_key=False,
            platform=vendor_profile["scrapli_platform"],
            timeout_socket=timeout,
            timeout_transport=timeout,
        )

        conn.open()

        if secret:
            conn.send_command("enable")
            conn.send_command(secret)

        for ic in vendor_profile["init_commands"]:
            conn.send_command(ic)

        response = conn.send_command(backup_cmd)
        # Anteponemos cabecera al comando principal igual que a los extra
        config = _build_section_header(backup_cmd) + (response.result or "")

        # Comandos extra
        if extra_commands:
            extra_output = []
            for cmd in extra_commands:
                if cancel_event is not None and cancel_event.is_set():
                    log('⏹ Cancelado por el usuario.', 'warning'); break
                try:
                    log(f"  ↳ Ejecutando comando extra: {cmd}", "info")
                    r = conn.send_command(cmd)
                    extra_output.append(_build_section_header(cmd) + (r.result or ""))
                except Exception as ce:
                    extra_output.append(_build_section_header(cmd) +
                                        f"!! Error ejecutando '{cmd}': {ce}")
            config += "".join(extra_output)

        for cc in vendor_profile["cleanup_commands"]:
            try:
                conn.send_command(cc)
            except Exception:
                pass

        conn.close()

        if not config or len(config) < 50:
            raise Exception("Config incompleta")

        log("✓ Backup obtenido con SCRAPLI", "success")
        return config, True, "", []

    except Exception as e:
        error_msg = str(e)
        if "No matching key exchange" in error_msg:
            log("⚠ Equipo legacy detectado (SSH KEX SHA1)", "warning")
            is_legacy = True
        log(f"Scrapli falló: {error_msg}", "warning")
        config = None

    # =========================================================
    # 🟡 SCRAPLI legacy (paramiko)
    # =========================================================
    if not config and not is_legacy:
        try:
            from scrapli import Scrapli
            log("Reintentando SCRAPLI con compatibilidad legacy...", "info")

            conn = Scrapli(
                host=host,
                auth_username=username,
                auth_password=password,
                auth_strict_key=False,
                platform=vendor_profile["scrapli_platform"],
                transport="paramiko",
                ssh_config_file=True,
                timeout_socket=timeout,
                timeout_transport=timeout,
            )
            conn.open()

            if secret:
                conn.send_command("enable")
                conn.send_command(secret)

            for ic in vendor_profile["init_commands"]:
                conn.send_command(ic)

            response = conn.send_command(backup_cmd)
            config = _build_section_header(backup_cmd) + (response.result or "")

            if extra_commands:
                extra_output = []
                for cmd in extra_commands:
                    try:
                        log(f"  ↳ Ejecutando comando extra: {cmd}", "info")
                        r = conn.send_command(cmd)
                        extra_output.append(_build_section_header(cmd) + (r.result or ""))
                    except Exception as ce:
                        extra_output.append(_build_section_header(cmd) +
                                            f"!! Error ejecutando '{cmd}': {ce}")
                config += "".join(extra_output)

            for cc in vendor_profile["cleanup_commands"]:
                try:
                    conn.send_command(cc)
                except Exception:
                    pass

            conn.close()

            if not config or len(config) < 50:
                raise Exception("Config incompleta")

            log("✓ Backup obtenido con SCRAPLI (modo legacy)", "success")
            return config, True, "", []

        except Exception as e:
            log(f"Scrapli legacy falló: {e}", "warning")
            config = None

    # =========================================================
    # 🟢 NETMIKO (FALLBACK FINAL)
    # =========================================================
    try:
        from netmiko import ConnectHandler

        if is_legacy:
            log("Usando NETMIKO (equipo legacy detectado)...", "warning")
        else:
            log("Usando NETMIKO (fallback)...", "warning")

        device = {
            "device_type": vendor_profile["device_type"],
            "host": host,
            "username": username,
            "password": password,
            "secret": secret if secret else password,
            "timeout": timeout,
            "global_delay_factor": 3,
            "fast_cli": False,
        }

        conn = ConnectHandler(**device)

        if not conn.check_enable_mode():
            try:
                conn.enable()
            except Exception:
                pass

        conn.set_base_prompt()
        conn.disable_paging()
        for ic in vendor_profile["init_commands"]:
            try:
                conn.send_command(ic, expect_string=r"#|>")
            except Exception:
                pass

        raw_main = conn.send_command(
            backup_cmd,
            read_timeout=180,
            strip_prompt=True,
            strip_command=True,
        )
        config = _build_section_header(backup_cmd) + (raw_main or "")

        if extra_commands:
            extra_output = []
            for cmd in extra_commands:
                try:
                    log(f"  ↳ Ejecutando comando extra: {cmd}", "info")
                    r = conn.send_command(cmd, read_timeout=120,
                                          strip_prompt=True, strip_command=True)
                    extra_output.append(_build_section_header(cmd) + (r or ""))
                except Exception as ce:
                    extra_output.append(_build_section_header(cmd) +
                                        f"!! Error ejecutando '{cmd}': {ce}")
            config += "".join(extra_output)

        for cc in vendor_profile["cleanup_commands"]:
            try:
                conn.send_command(cc)
            except Exception:
                pass

        conn.disconnect()

        if not config or len(config) < 50:
            raise Exception("Config incompleta")

        log("✓ Backup obtenido con NETMIKO", "success")
        return config, True, "", []

    except Exception as e:
        return None, False, str(e)


def _exos_send_long(conn, cmd, log, label="comando"):
    """
    Envío robusto de comandos cuya salida es muy grande en EXOS.
    Estrategia:
      1) send_command_timing con `last_read` (espera silencio del canal).
      2) Si el resultado es sospechosamente corto, hace fallback a
         send_command con read_timeout largo y cmd_verify=False.
    Esto evita que Netmiko corte la salida cuando detecta un '#' que
    aparece dentro del propio config (falso positivo de prompt) o
    cuando se agota el read_timeout por defecto.
    """
    out = ""
    # --- Estrategia 1: send_command_timing -----------------------------
    try:
        # Netmiko 4.x soporta last_read y read_timeout.
        try:
            out = conn.send_command_timing(
                cmd,
                delay_factor=4,
                last_read=8.0,
                read_timeout=600,
                strip_prompt=True,
                strip_command=True,
            )
        except TypeError:
            # Netmiko <4.x — sin last_read/read_timeout
            out = conn.send_command_timing(
                cmd,
                delay_factor=4,
                max_loops=8000,
                strip_prompt=True,
                strip_command=True,
            )
    except Exception as e:
        log(f"  ⚠ send_command_timing falló para {label}: {e}", "warning")
        out = ""

    # --- Estrategia 2: si vino vacío o muy corto, reintentar ----------
    if not out or len(out.strip()) < 80:
        log(f"  ↻ Salida de '{cmd}' parece truncada — reintentando con send_command...", "warning")
        try:
            out = conn.send_command(
                cmd,
                read_timeout=600,
                strip_prompt=True,
                strip_command=True,
                cmd_verify=False,
                expect_string=r"#\s*$",
            )
        except Exception as e:
            log(f"  ✗ send_command fallback también falló: {e}", "error")

    return out or ""


def backup_device_extreme(host, username, password, secret, timeout,
                          extra_commands, vendor_profile, log,
                          cancel_event=None):
    """
    Respaldo Extreme Networks (EXOS) — usa Netmiko directamente.
    Aplica:
        - disable_paging() nativo de Netmiko + 'disable clipaging' explícito
        - show configuration (comando principal, capturado con timing robusto)
        - comandos extra del usuario
        - enable clipaging   (restaura el comportamiento por defecto)
    """
    backup_cmd = vendor_profile["backup_commands"][0]

    try:
        from netmiko import ConnectHandler

        log("Conectando a switch Extreme vía NETMIKO...", "info")

        device = {
            "device_type": vendor_profile["device_type"],   # extreme_exos
            "host": host,
            "username": username,
            "password": password,
            "timeout": timeout,
            "global_delay_factor": 3,
            "fast_cli": False,
        }

        conn = ConnectHandler(**device)
        conn.set_base_prompt()

        # ── Desactivar paginación de la forma más confiable posible ──
        # 1) Método del driver de Netmiko (sabe el comando correcto para EXOS)
        try:
            conn.disable_paging(command="disable clipaging")
            log("  · disable_paging() OK", "info")
        except Exception as e:
            log(f"  ⚠ disable_paging() falló: {e}", "warning")

        # 2) Comandos init del perfil (por si el usuario añadió más)
        for ic in vendor_profile["init_commands"]:
            log(f"  · init: {ic}", "info")
            try:
                conn.send_command_timing(ic, delay_factor=2)
            except Exception as ie:
                log(f"  ⚠ Falló init '{ic}': {ie}", "warning")

        # ── Comando principal de respaldo (output grande, usar timing) ─
        log(f"Ejecutando '{backup_cmd}' (puede tomar tiempo)...", "info")
        raw_main = _exos_send_long(conn, backup_cmd, log, label="config principal")
        log(f"  · {len(raw_main)} caracteres capturados", "info")
        config = _build_section_header(backup_cmd) + (raw_main or "")

        # ── Comandos extra del usuario (tracking + cancelación) ───────
        cmd_results = []
        if extra_commands:
            total_cmds = len(extra_commands)
            extra_output = []
            for i, cmd in enumerate(extra_commands, 1):
                if cancel_event is not None and cancel_event.is_set():
                    log(f"⏹ Cancelado por el usuario — se omiten "
                        f"{total_cmds - i + 1} comando(s) restantes.", "warning")
                    for c in extra_commands[i-1:]:
                        cmd_results.append({
                            "cmd": c, "ok": False, "chars": 0,
                            "elapsed": 0.0, "error": "cancelado por usuario",
                        })
                    break

                settle, mw = _cmd_timeouts(cmd, transport="ssh")
                weight = "pesado" if _is_heavy_cmd(cmd) else "normal"
                log(f"▶ [{i}/{total_cmds}] {cmd} "
                    f"({weight}, hasta {int(mw)}s)", "info")

                t0 = time.time()
                try:
                    # Usamos send_command_timing directamente con
                    # tiempos adaptativos para comandos pesados
                    r = conn.send_command_timing(
                        cmd,
                        delay_factor=6 if _is_heavy_cmd(cmd) else 4,
                        last_read=settle,
                        strip_prompt=True,
                        strip_command=True,
                    )
                    elapsed = time.time() - t0
                    n_chars = len(r.strip()) if r else 0
                    if n_chars < 5:
                        log(f"  ⚠ {cmd} — respuesta vacía en "
                            f"{elapsed:.1f}s", "warning")
                        cmd_results.append({
                            "cmd": cmd, "ok": False, "chars": n_chars,
                            "elapsed": elapsed,
                            "error": "respuesta vacía",
                        })
                        extra_output.append(
                            _build_section_header(cmd) + "!! Respuesta vacía\n"
                        )
                    else:
                        log(f"  ✓ {cmd} — {n_chars} chars en "
                            f"{elapsed:.1f}s", "success")
                        cmd_results.append({
                            "cmd": cmd, "ok": True, "chars": n_chars,
                            "elapsed": elapsed, "error": "",
                        })
                        extra_output.append(
                            _build_section_header(cmd) + (r or "")
                        )
                except Exception as ce:
                    elapsed = time.time() - t0
                    log(f"  ✗ {cmd} — error: {ce}", "error")
                    cmd_results.append({
                        "cmd": cmd, "ok": False, "chars": 0,
                        "elapsed": elapsed, "error": str(ce),
                    })
                    extra_output.append(
                        _build_section_header(cmd) +
                        f"!! Error ejecutando '{cmd}': {ce}\n"
                    )
            config += "".join(extra_output)

        # ── Cleanup (enable clipaging) ─────────────────────────────────
        for cc in vendor_profile["cleanup_commands"]:
            log(f"  · cleanup: {cc}", "info")
            try:
                conn.send_command_timing(cc, delay_factor=2)
            except Exception as ce:
                log(f"  ⚠ Falló cleanup '{cc}': {ce}", "warning")

        conn.disconnect()

        if not config or len(config) < 30:
            return None, False, "Config Extreme vacía o incompleta."

        log("✓ Backup obtenido con NETMIKO (Extreme EXOS)", "success")
        return config, True, "", cmd_results

    except Exception as e:
        return None, False, str(e), []


# ─────────────────────────────────────────────────────────────
#  RESPALDO POR CONSOLA (SERIAL)
# ─────────────────────────────────────────────────────────────
def list_serial_ports() -> list:
    """Devuelve lista de puertos COM disponibles (vacía si pyserial no está)."""
    if not PYSERIAL_AVAILABLE:
        return []
    try:
        return [p.device for p in serial.tools.list_ports.comports()]
    except Exception:
        return []


def _serial_drain(ser, settle: float = 0.8, max_wait: float = 60.0) -> str:
    """
    Lee del puerto serial hasta que el flujo se queda en silencio
    durante `settle` segundos.  Devuelve todo lo acumulado como str.
    """
    chunks = []
    last_data_at = time.time()
    started_at   = time.time()

    while True:
        try:
            n = ser.in_waiting
        except Exception:
            n = 0

        if n:
            data = ser.read(n)
            if data:
                chunks.append(data)
                last_data_at = time.time()
        else:
            # Sin datos disponibles — pequeña espera
            time.sleep(0.1)

        # Condición de salida: silencio prolongado
        if time.time() - last_data_at >= settle:
            break
        # Tope absoluto por seguridad
        if time.time() - started_at >= max_wait:
            break

    raw = b"".join(chunks)
    return raw.decode("utf-8", errors="ignore")


# ─────────────────────────────────────────────────────────────
#  RETRY / REINTENTO DE COMANDOS FALLIDOS
# ─────────────────────────────────────────────────────────────
#  Estas funciones NO hacen un backup completo — solo reconectan al
#  equipo, corren un subconjunto de comandos (los que fallaron en un
#  respaldo anterior), y anexan la salida al archivo original con un
#  separador RETRY.  Es la implementación del botón "↻ Reintentar
#  fallidos" que permite recuperar solo lo que faltó sin repetir todo.
# ─────────────────────────────────────────────────────────────
def _append_retry_section(filepath: str, vendor: str,
                          extra_output: str) -> None:
    """Anexa una sección RETRY al archivo de respaldo original."""
    if not filepath or not os.path.exists(filepath):
        return
    banner = (
        "\n\n"
        "! ============================================================\n"
        f"!  RETRY / Reintento de comandos fallidos\n"
        f"!  Fecha : {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
        f"!  Vendor: {vendor}\n"
        "! ============================================================\n"
    )
    with open(filepath, "a", encoding="utf-8") as f:
        f.write(banner)
        f.write(extra_output)


def retry_ssh_commands(host, username, password, secret, timeout,
                       vendor, commands, append_to_file,
                       log_callback=None, cancel_event=None):
    """
    Reintenta ejecutar un conjunto de comandos por SSH.
    Retorna dict {success, cmd_results, message}.
    """
    def log(msg, level="info"):
        if log_callback:
            log_callback(host, msg, level)

    result = {"success": False, "cmd_results": [], "message": "",
              "filename": append_to_file}

    if vendor not in VENDORS:
        result["message"] = f"Vendor desconocido: {vendor}"
        return result
    vendor_profile = VENDORS[vendor]

    try:
        from netmiko import ConnectHandler
        log(f"[RETRY] Conectando vía SSH ({vendor})...", "info")

        device_type = vendor_profile["device_type"]
        device = {
            "device_type": device_type,
            "host": host,
            "username": username,
            "password": password,
            "secret": secret if secret else password,
            "timeout": timeout,
            "global_delay_factor": 3,
            "fast_cli": False,
        }
        conn = ConnectHandler(**device)

        if vendor == "Cisco":
            if not conn.check_enable_mode():
                try: conn.enable()
                except Exception: pass
            try: conn.disable_paging()
            except Exception: pass
        else:  # Extreme
            try:
                conn.disable_paging(command="disable clipaging")
            except Exception:
                pass

        for ic in vendor_profile["init_commands"]:
            try:
                conn.send_command_timing(ic, delay_factor=2)
            except Exception:
                pass

        # Ejecutar los comandos indicados
        cmd_results = []
        extra_output_parts = []
        total = len(commands)
        for i, cmd in enumerate(commands, 1):
            if cancel_event is not None and cancel_event.is_set():
                log(f"⏹ RETRY cancelado — {total - i + 1} restantes.",
                    "warning")
                for c in commands[i-1:]:
                    cmd_results.append({
                        "cmd": c, "ok": False, "chars": 0,
                        "elapsed": 0.0, "error": "cancelado por usuario",
                    })
                break

            settle, mw = _cmd_timeouts(cmd, transport="ssh")
            weight = "pesado" if _is_heavy_cmd(cmd) else "normal"
            log(f"▶ RETRY [{i}/{total}] {cmd} ({weight}, "
                f"hasta {int(mw)}s)", "info")

            t0 = time.time()
            try:
                r = conn.send_command_timing(
                    cmd,
                    delay_factor=6 if _is_heavy_cmd(cmd) else 4,
                    last_read=settle,
                    strip_prompt=True,
                    strip_command=True,
                )
                elapsed = time.time() - t0
                n_chars = len(r.strip()) if r else 0
                if n_chars < 5:
                    log(f"  ⚠ {cmd} — respuesta vacía "
                        f"({elapsed:.1f}s)", "warning")
                    cmd_results.append({
                        "cmd": cmd, "ok": False, "chars": n_chars,
                        "elapsed": elapsed, "error": "respuesta vacía",
                    })
                    extra_output_parts.append(
                        _build_section_header(cmd) + "!! Respuesta vacía\n"
                    )
                else:
                    log(f"  ✓ {cmd} — {n_chars} chars en "
                        f"{elapsed:.1f}s", "success")
                    cmd_results.append({
                        "cmd": cmd, "ok": True, "chars": n_chars,
                        "elapsed": elapsed, "error": "",
                    })
                    extra_output_parts.append(
                        _build_section_header(cmd) + (r or "")
                    )
            except Exception as ce:
                elapsed = time.time() - t0
                log(f"  ✗ {cmd} — {ce}", "error")
                cmd_results.append({
                    "cmd": cmd, "ok": False, "chars": 0,
                    "elapsed": elapsed, "error": str(ce),
                })
                extra_output_parts.append(
                    _build_section_header(cmd) +
                    f"!! Error ejecutando '{cmd}': {ce}\n"
                )

        # Cleanup
        for cc in vendor_profile["cleanup_commands"]:
            try:
                conn.send_command_timing(cc, delay_factor=2)
            except Exception:
                pass
        conn.disconnect()

        # Anexar al archivo original
        _append_retry_section(append_to_file, vendor,
                              "".join(extra_output_parts))

        result["success"] = True
        result["cmd_results"] = cmd_results
        result["message"] = f"Retry OK sobre {append_to_file}"
        log(f"✓ RETRY completado. Se anexó al archivo original.", "success")

    except Exception as e:
        result["message"] = str(e)
        log(f"✗ Error en RETRY: {e}", "error")

    return result


def retry_serial_commands(port, baudrate, username, password, secret,
                          vendor, commands, append_to_file,
                          bytesize=8, parity="N", stopbits=1,
                          log_callback=None, cancel_event=None):
    """
    Reintenta ejecutar un conjunto de comandos por consola serial.
    """
    def log(msg, level="info"):
        if log_callback:
            log_callback(port, msg, level)

    result = {"success": False, "cmd_results": [], "message": "",
              "filename": append_to_file}

    if not PYSERIAL_AVAILABLE:
        result["message"] = "pyserial no está instalado."
        log(result["message"], "error")
        return result
    if vendor not in VENDORS:
        result["message"] = f"Vendor desconocido: {vendor}"
        return result

    vendor_profile = VENDORS[vendor]
    parity_map = {"N": serial.PARITY_NONE, "E": serial.PARITY_EVEN,
                  "O": serial.PARITY_ODD}
    bytesize_map = {7: serial.SEVENBITS, 8: serial.EIGHTBITS}
    stopbits_map = {1: serial.STOPBITS_ONE, 2: serial.STOPBITS_TWO}

    ser = None
    try:
        log(f"[RETRY] Abriendo puerto serial {port}...", "info")
        ser = serial.Serial(
            port=port,
            baudrate=baudrate,
            bytesize=bytesize_map.get(bytesize, serial.EIGHTBITS),
            parity=parity_map.get(parity, serial.PARITY_NONE),
            stopbits=stopbits_map.get(stopbits, serial.STOPBITS_ONE),
            timeout=1,
        )
        time.sleep(0.4)
        try:
            ser.reset_input_buffer()
            ser.reset_output_buffer()
        except Exception:
            pass

        ok = _serial_login(ser, username, password, log)
        if not ok:
            result["message"] = "Fallo de login por consola en RETRY."
            return result

        if vendor == "Cisco":
            sample = _serial_send(ser, "", settle=0.8, max_wait=4)
            if re.search(r">\s*$", sample):
                out = _serial_send(ser, "enable", settle=1.2, max_wait=8)
                if "password" in out.lower():
                    _serial_send(ser, secret or password, settle=1.2, max_wait=8)

        for ic in vendor_profile["init_commands"]:
            _serial_send(ser, ic, settle=0.8, max_wait=10)

        cmd_results = []
        extra_output_parts = []
        total = len(commands)
        for i, cmd in enumerate(commands, 1):
            if cancel_event is not None and cancel_event.is_set():
                log(f"⏹ RETRY cancelado — {total - i + 1} restantes.",
                    "warning")
                for c in commands[i-1:]:
                    cmd_results.append({
                        "cmd": c, "ok": False, "chars": 0,
                        "elapsed": 0.0, "error": "cancelado por usuario",
                    })
                break

            settle, mw = _cmd_timeouts(cmd, transport="serial")
            weight = "pesado" if _is_heavy_cmd(cmd) else "normal"
            log(f"▶ RETRY [{i}/{total}] {cmd} ({weight}, "
                f"hasta {int(mw)}s)", "info")

            t0 = time.time()
            try:
                out = _serial_send(ser, cmd, settle=settle, max_wait=mw)
                out = re.sub(
                    r"^[^\n]*" + re.escape(cmd) + r"[^\n]*\n",
                    "", out, count=1
                )
                elapsed = time.time() - t0
                n_chars = len(out.strip())
                if n_chars < 5:
                    log(f"  ⚠ {cmd} — respuesta vacía "
                        f"({elapsed:.1f}s)", "warning")
                    cmd_results.append({
                        "cmd": cmd, "ok": False, "chars": n_chars,
                        "elapsed": elapsed, "error": "respuesta vacía",
                    })
                    extra_output_parts.append(
                        _build_section_header(cmd) + "!! Respuesta vacía\n"
                    )
                else:
                    log(f"  ✓ {cmd} — {n_chars} chars en "
                        f"{elapsed:.1f}s", "success")
                    cmd_results.append({
                        "cmd": cmd, "ok": True, "chars": n_chars,
                        "elapsed": elapsed, "error": "",
                    })
                    extra_output_parts.append(
                        _build_section_header(cmd) + out
                    )
                _serial_drain(ser, settle=0.5, max_wait=3)
            except Exception as ce:
                elapsed = time.time() - t0
                log(f"  ✗ {cmd} — {ce}", "error")
                cmd_results.append({
                    "cmd": cmd, "ok": False, "chars": 0,
                    "elapsed": elapsed, "error": str(ce),
                })
                extra_output_parts.append(
                    _build_section_header(cmd) +
                    f"!! Error ejecutando '{cmd}': {ce}\n"
                )

        for cc in vendor_profile["cleanup_commands"]:
            _serial_send(ser, cc, settle=0.8, max_wait=10)
        try:
            _serial_send(ser, "exit", settle=0.8, max_wait=5)
            if vendor == "Cisco":
                _serial_send(ser, "exit", settle=0.8, max_wait=5)
        except Exception:
            pass

        # Normalizar salida y anexar
        merged = _clean_serial_output("".join(extra_output_parts))
        _append_retry_section(append_to_file, vendor, merged)

        result["success"] = True
        result["cmd_results"] = cmd_results
        result["message"] = f"Retry OK sobre {append_to_file}"
        log("✓ RETRY completado. Se anexó al archivo original.", "success")

    except Exception as e:
        result["message"] = str(e)
        log(f"✗ Error en RETRY: {e}", "error")
    finally:
        try:
            if ser and ser.is_open:
                ser.close()
        except Exception:
            pass

    return result


# ─────────────────────────────────────────────────────────────
#  COMANDOS "PESADOS" Y TIMEOUTS ADAPTABLES
# ─────────────────────────────────────────────────────────────
#  Algunos comandos generan mucho output (5000+ líneas) y necesitan
#  timeouts extendidos.  Detectamos por keywords en el propio comando.
#  Si no coincide con "pesado", usamos timeouts "normales".
# ─────────────────────────────────────────────────────────────
_HEAVY_CMD_KEYWORDS = [
    "tech-support", "tech support", "sh tech", "show tech",
    "log messages", "show log", "show logging",
    "show tech-support all",
    "show fdb", "show mac address-table",
    "show configuration detail",
]


def _is_heavy_cmd(cmd: str) -> bool:
    """True si el comando probablemente genere output enorme."""
    if not cmd:
        return False
    low = cmd.lower()
    return any(k in low for k in _HEAVY_CMD_KEYWORDS)


def _cmd_timeouts(cmd: str, transport: str = "ssh") -> tuple:
    """
    Devuelve (settle_seg, max_wait_seg) para un comando dado.
    - transport="serial" : consola física, más lenta -> tiempos mayores
    - transport="ssh"    : conexión de red, más rápida
    Comandos "pesados" (show tech-support, log messages, etc.) usan
    hasta 20 minutos porque un show tech-support en Extreme puede
    tardar varios minutos y generar 5000-8000 líneas.
    """
    heavy = _is_heavy_cmd(cmd)
    if transport == "serial":
        if heavy:
            return (10.0, 1200.0)   # 20 min
        return (3.0, 300.0)         # 5 min
    else:  # ssh
        if heavy:
            return (12.0, 1200.0)   # 20 min
        return (8.0, 300.0)         # 5 min


def _serial_send(ser, cmd: str, settle: float = 1.0,
                 max_wait: float = 120.0) -> str:
    """
    Envía un comando seguido de un único CR ('\\r') y devuelve la
    salida acumulada.

    NOTA IMPORTANTE: usamos sólo '\\r' (no '\\r\\n') porque PuTTY hace
    exactamente eso en sesiones seriales por defecto.  Mandar '\\r\\n'
    causa que muchos equipos (EXOS, IOS clásicos) interpreten el '\\n'
    como un segundo Enter: al enviar el password, el LF extra equivale
    a Enter en el prompt de password, lo que confunde el login y
    termina con 'Login incorrect' aunque las credenciales sean válidas.
    Con sólo '\\r' replicamos la conducta de PuTTY al pie de la letra.
    """
    line = (cmd.rstrip("\r\n") + "\r").encode("utf-8", errors="ignore")
    ser.write(line)
    ser.flush()
    return _serial_drain(ser, settle=settle, max_wait=max_wait)


# ─────────────────────────────────────────────────────────────
#  LIMPIEZA DE OUTPUT SERIAL
# ─────────────────────────────────────────────────────────────
# Regex para quitar códigos de escape ANSI (colores y posicionamiento).
_ANSI_RE = re.compile(
    r"\x1b(?:\[[0-?]*[ -/]*[@-~]|[@-~]|[\(\)][0-9A-Za-z])"
)


def _clean_serial_output(text: str) -> str:
    """
    Normaliza la salida cruda de un puerto serial para que el archivo
    de respaldo se vea como el que produce SSH:
      - quita códigos ANSI (colores, cursor)
      - convierte \\r\\n y \\r solitarios a \\n
      - quita whitespace al final de cada línea
      - colapsa 3+ líneas en blanco consecutivas a una sola
    """
    if not text:
        return text
    text = _ANSI_RE.sub("", text)
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = "\n".join(line.rstrip() for line in text.split("\n"))
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text


# Patrones REALES de fallo de login (regex con word boundaries).
# Se evita coincidir con frases inocuas del banner como
# "0 failed logins since last successful login" (EXOS).
_LOGIN_FAIL_PATTERNS = [
    r"\blogin\s+incorrect\b",
    r"\bauthentication\s+failed\b",
    r"\bauthentication\s+failure\b",
    r"\bauthentication\s+error\b",
    r"\binvalid\s+(login|password|user(name)?|credentials?)\b",
    r"\baccess\s+denied\b",
    r"\bpermission\s+denied\b",
    r"\bbad\s+password\b",
    r"\bincorrect\s+password\b",
    r"\busername\s+(or|and)\s+password\b.*\b(incorrect|invalid|wrong)\b",
    r"\baccount\s+(is\s+)?locked\b",
    r"\bauthentication\s+rejected\b",
]


def _has_prompt(text: str) -> bool:
    """
    Devuelve True si las ÚLTIMAS líneas del buffer terminan en un prompt
    típico de switch ('#' o '>').  Mira sólo las últimas 6 líneas no
    vacías para no confundirse con caracteres '#' dentro de banners.
    Quita códigos ANSI antes de evaluar.
    """
    if not text:
        return False
    text = _ANSI_RE.sub("", text)
    lines = [l for l in text.splitlines() if l.strip()]
    if not lines:
        return False
    tail = "\n".join(lines[-6:])
    return bool(re.search(r"[#>]\s*$", tail))


def _saw_login_again(text: str) -> bool:
    """
    Devuelve True si las últimas líneas del buffer terminan en un
    prompt 'login:' o 'username:' — esto es señal DEFINITIVA de fallo
    porque significa que el equipo nos rechazó y nos devolvió al
    inicio del login.
    """
    if not text:
        return False
    text = _ANSI_RE.sub("", text).lower()
    lines = [l for l in text.splitlines() if l.strip()]
    if not lines:
        return False
    tail = "\n".join(lines[-4:])
    return bool(re.search(r"(login|username)\s*:\s*$", tail))


def _looks_like_failure(text: str) -> bool:
    low = text.lower()
    for pat in _LOGIN_FAIL_PATTERNS:
        if re.search(pat, low):
            return True
    return False


def _wait_for_prompt(ser, max_wait: float = 20.0,
                     silence: float = 1.5) -> str:
    """
    Lee del puerto hasta que el buffer termina en un prompt ('#' o '>'),
    o vemos un login: nuevamente (fallo), o se cumple max_wait.
    Devuelve el texto acumulado.
    """
    chunks = []
    started = time.time()
    last_data = time.time()

    while time.time() - started < max_wait:
        try:
            n = ser.in_waiting
        except Exception:
            n = 0
        if n:
            chunks.append(ser.read(n))
            last_data = time.time()
            # Comprobar si ya llegamos al prompt
            text = b"".join(chunks).decode("utf-8", errors="ignore")
            if _has_prompt(text):
                # Dar un pequeño respiro por si vienen más bytes (banner)
                time.sleep(0.3)
                try:
                    extra = ser.read(ser.in_waiting)
                    if extra:
                        chunks.append(extra)
                except Exception:
                    pass
                return b"".join(chunks).decode("utf-8", errors="ignore")
            if _saw_login_again(text):
                return text
        else:
            time.sleep(0.1)
            # Si llevamos `silence` segundos sin datos Y ya tenemos
            # algo capturado, salimos.
            if (time.time() - last_data) >= silence and chunks:
                break

    return b"".join(chunks).decode("utf-8", errors="ignore")


def _serial_login(ser, username: str, password: str, log) -> bool:
    """
    Detecta y completa el login por consola.
    Estrategia:
      1) "Despertar" la consola con varios CR (3 Enters) con pausa
         entre ellos — algunos equipos no muestran nada hasta recibir
         una tecla.
      2) Drenar y revisar:
           - si ya hay prompt ('#'/'>') => sesión abierta
           - si pide 'login:' => mandar usuario
           - si pide 'password:' => mandar password (o sólo Enter)
      3) Esperar al prompt con un loop por deadline (no por silencio
         únicamente), considerando estos finales posibles:
           - prompt visible => OK
           - 'login:' aparece de nuevo => fallo definitivo (rechazado)
           - patrón explícito de fallo (Login incorrect, etc) => fallo
           - nada en absoluto => beneficio de la duda
    """
    # 1) Despertar consola: 3 CR con pausas
    log("  · Despertando consola (3 Enters)...", "info")
    for _ in range(3):
        try:
            ser.write(b"\r")
            ser.flush()
        except Exception:
            pass
        time.sleep(0.8)

    banner = _serial_drain(ser, settle=2.0, max_wait=8)

    # Si ya hay prompt al final, estamos dentro.
    if _has_prompt(banner):
        log("  · Sesión ya abierta en consola, no se requiere login.", "info")
        return True

    out = banner
    low = banner.lower()

    # 2) Login: enviar usuario si el equipo lo pidió.
    if "login:" in low or "username:" in low:
        log(f"  · Enviando usuario: {username}", "info")
        out = _serial_send(ser, username, settle=1.5, max_wait=10)
        low = out.lower()

    # 3) Password: si el equipo lo pide, mandamos lo que haya
    #    (incluyendo cadena vacía → sólo Enter).
    if "password" in low:
        if password:
            log("  · Enviando password...", "info")
        else:
            log("  · Password vacío — enviando sólo Enter.", "info")
        out = _serial_send(ser, password, settle=1.0, max_wait=8)

    # 4) Esperar al prompt definitivo (loop por deadline)
    log("  · Esperando prompt del equipo...", "info")
    post = _wait_for_prompt(ser, max_wait=18.0, silence=2.0)
    combined = out + post

    # 5) Decisión final: prompt > login: de vuelta > patrón de fallo > duda
    if _has_prompt(combined):
        log("  ✓ Prompt detectado, login OK.", "success")
        return True

    if _saw_login_again(combined):
        log("  ✗ El equipo volvió a pedir 'login:' — credenciales rechazadas.",
            "error")
        return False

    if _looks_like_failure(combined):
        log("  ✗ Mensaje explícito de fallo en la respuesta del equipo.", "error")
        return False

    # 6) Último intento: mandar Enter y revisar otra vez
    extra = _serial_send(ser, "", settle=1.5, max_wait=5)
    final = combined + extra
    if _has_prompt(final):
        log("  ✓ Prompt detectado tras Enter extra.", "success")
        return True
    if _saw_login_again(final):
        log("  ✗ El equipo volvió a pedir 'login:' — credenciales rechazadas.",
            "error")
        return False
    if _looks_like_failure(final):
        log("  ✗ Mensaje explícito de fallo en la respuesta del equipo.", "error")
        return False

    log("  ⚠ No se detectó un prompt claro tras el login; se continuará igualmente.",
        "warning")
    return True   # damos el beneficio de la duda


def backup_via_serial(port: str, baudrate: int,
                      username: str, password: str,
                      secret: str, vendor: str,
                      extra_commands: list,
                      output_dir: str,
                      log_callback=None,
                      bytesize: int = 8, parity: str = "N",
                      stopbits: int = 1,
                      cancel_event=None) -> dict:
    """
    Respaldo por consola serial (uno-a-uno, sin pre-checks de red).
    Usa pyserial directamente — funciona igual con Cisco que con Extreme.
    """
    def log(msg, level="info"):
        if log_callback:
            log_callback(port, msg, level)

    result = {"success": False, "host": port, "filename": "",
              "message": "", "vendor": vendor, "transport": "serial"}

    if not PYSERIAL_AVAILABLE:
        result["message"] = ("pyserial no está instalado. "
                             "Instala con:  pip install pyserial")
        log(result["message"], "error")
        return result

    if vendor not in VENDORS:
        result["message"] = f"Vendor desconocido: {vendor}"
        log(result["message"], "error")
        return result

    vendor_profile = VENDORS[vendor]
    backup_cmd     = vendor_profile["backup_commands"][0]
    extra_commands = extra_commands or []

    # Mapear parity de char a constante pyserial
    parity_map = {
        "N": serial.PARITY_NONE,
        "E": serial.PARITY_EVEN,
        "O": serial.PARITY_ODD,
    }
    bytesize_map = {7: serial.SEVENBITS, 8: serial.EIGHTBITS}
    stopbits_map = {1: serial.STOPBITS_ONE, 2: serial.STOPBITS_TWO}

    ser = None
    try:
        log(f"Abriendo puerto serial {port} @ {baudrate}-{bytesize}{parity}{stopbits}...",
            "info")
        ser = serial.Serial(
            port=port,
            baudrate=baudrate,
            bytesize=bytesize_map.get(bytesize, serial.EIGHTBITS),
            parity=parity_map.get(parity, serial.PARITY_NONE),
            stopbits=stopbits_map.get(stopbits, serial.STOPBITS_ONE),
            timeout=1,
            xonxoff=False,
            rtscts=False,
            dsrdtr=False,
        )
        # Pequeña pausa para que el adaptador USB-Serial se estabilice
        time.sleep(0.4)
        try:
            ser.reset_input_buffer()
            ser.reset_output_buffer()
        except Exception:
            pass

        # Login (si aplica)
        ok = _serial_login(ser, username, password, log)
        if not ok:
            result["message"] = "Fallo de login por consola."
            return result

        # Cisco: si el prompt es '>', subir a enable
        sample = _serial_send(ser, "", settle=0.8, max_wait=4)
        if vendor == "Cisco" and re.search(r">\s*$", sample):
            log("  · Modo usuario detectado, escalando a enable...", "info")
            out = _serial_send(ser, "enable", settle=1.2, max_wait=8)
            if "password" in out.lower():
                _serial_send(ser, secret or password, settle=1.2, max_wait=8)

        # Init commands (disable clipaging, terminal length 0, etc.)
        for ic in vendor_profile["init_commands"]:
            log(f"  · init: {ic}", "info")
            _serial_send(ser, ic, settle=0.8, max_wait=10)

        # Comando principal de respaldo
        log(f"Ejecutando '{backup_cmd}' por consola (puede tomar tiempo)...", "info")
        # Para configs grandes damos más tiempo de silencio y máximo
        main = _serial_send(ser, backup_cmd, settle=4.0, max_wait=600)
        # Quitamos el eco del comando si aparece al inicio
        main = re.sub(r"^[^\n]*" + re.escape(backup_cmd) + r"[^\n]*\n",
                      "", main, count=1)
        log(f"  · {len(main)} caracteres capturados", "info")

        # Cabecera del comando principal, igual que los extras
        config = _build_section_header(backup_cmd) + main

        # Comandos extra — con tracking, cancelación y timeouts adaptables
        cmd_results = []
        if extra_commands:
            total_cmds = len(extra_commands)
            for i, cmd in enumerate(extra_commands, 1):
                # ¿Cancelación solicitada por el usuario?
                if cancel_event is not None and cancel_event.is_set():
                    log(f"⏹ Cancelado por el usuario — se omiten "
                        f"{total_cmds - i + 1} comando(s) restantes.", "warning")
                    for c in extra_commands[i-1:]:
                        cmd_results.append({
                            "cmd": c, "ok": False, "chars": 0,
                            "elapsed": 0.0, "error": "cancelado por usuario",
                        })
                    break

                settle, mw = _cmd_timeouts(cmd, transport="serial")
                weight = "pesado" if _is_heavy_cmd(cmd) else "normal"
                log(f"▶ [{i}/{total_cmds}] {cmd} "
                    f"({weight}, hasta {int(mw)}s)", "info")

                t0 = time.time()
                try:
                    out = _serial_send(ser, cmd, settle=settle, max_wait=mw)
                    # Quitar el eco del comando
                    out = re.sub(
                        r"^[^\n]*" + re.escape(cmd) + r"[^\n]*\n",
                        "", out, count=1
                    )
                    elapsed = time.time() - t0
                    n_chars = len(out.strip())
                    if n_chars < 5:
                        log(f"  ⚠ {cmd} — respuesta vacía "
                            f"({elapsed:.1f}s)", "warning")
                        cmd_results.append({
                            "cmd": cmd, "ok": False, "chars": n_chars,
                            "elapsed": elapsed,
                            "error": "respuesta vacía",
                        })
                        config += _build_section_header(cmd) +                                   "!! Respuesta vacía\n"
                    else:
                        log(f"  ✓ {cmd} — {n_chars} chars en "
                            f"{elapsed:.1f}s", "success")
                        cmd_results.append({
                            "cmd": cmd, "ok": True, "chars": n_chars,
                            "elapsed": elapsed, "error": "",
                        })
                        config += _build_section_header(cmd) + out

                    # Drenar cualquier basura residual antes del próximo cmd
                    _serial_drain(ser, settle=0.5, max_wait=3)
                except Exception as ce:
                    elapsed = time.time() - t0
                    log(f"  ✗ {cmd} — error: {ce}", "error")
                    cmd_results.append({
                        "cmd": cmd, "ok": False, "chars": 0,
                        "elapsed": elapsed, "error": str(ce),
                    })
                    config += _build_section_header(cmd) +                               f"!! Error ejecutando '{cmd}': {ce}\n"

        # Guardar tracking para posibles reintentos
        result["cmd_results"] = cmd_results

        # Cleanup commands del vendor (restaurar paginación, etc.)
        for cc in vendor_profile["cleanup_commands"]:
            log(f"  · cleanup: {cc}", "info")
            _serial_send(ser, cc, settle=0.8, max_wait=10)

        # ── Cerrar sesión en el equipo antes de cerrar el puerto ──
        # Cisco/Extreme: 'exit' suele ser suficiente.  En Cisco si
        # estamos en enable mode, un solo 'exit' nos baja a user mode
        # y un segundo 'exit' termina la sesión.  En Extreme un solo
        # 'exit' (o 'logout' / 'quit') cierra la sesión.
        log("  · Cerrando sesión en el equipo (exit)...", "info")
        try:
            _serial_send(ser, "exit", settle=0.8, max_wait=5)
            if vendor == "Cisco":
                # Por si estábamos en enable, mandar un segundo exit
                _serial_send(ser, "exit", settle=0.8, max_wait=5)
        except Exception as ex:
            log(f"  ⚠ No se pudo enviar exit: {ex}", "warning")

        if not config or len(config.strip()) < 30:
            result["message"] = "Respaldo por consola vacío o muy corto."
            log(result["message"], "error")
            return result

        # ── Normalizar la salida (igual visual que SSH) ──
        # Quita ANSI, normaliza \r\n, colapsa líneas en blanco múltiples.
        config = _clean_serial_output(config)

        # Guardado
        hostname = "console_device"
        if vendor == "Cisco":
            m = re.search(r"hostname\s+(\S+)", config)
            if m: hostname = m.group(1)
        else:
            m = (re.search(r'configure snmp sysname\s+"([^"]+)"', config, re.I) or
                 re.search(r'sysname\s+"?([^"\n\r]+)"?', config, re.I))
            if m: hostname = m.group(1).strip().split()[0]

        timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
        vtag = vendor.lower()
        port_tag = re.sub(r"[^A-Za-z0-9]+", "", port)  # 'COM3' -> 'COM3'
        filename = f"{hostname}_{port_tag}_{vtag}_console_{timestamp}.txt"
        filepath = os.path.join(output_dir, filename)
        os.makedirs(output_dir, exist_ok=True)

        header = (
            f"! ============================================================\n"
            f"!  Backup de configuración (CONSOLA SERIAL)\n"
            f"!  Vendor    : {vendor}\n"
            f"!  Puerto    : {port}  @ {baudrate} {bytesize}{parity}{stopbits}\n"
            f"!  Hostname  : {hostname}\n"
            f"!  Fecha     : {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
            f"!  Generado  : Multi-Vendor Backup Tool v1.8\n"
            f"! ============================================================\n\n"
        )
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(header)
            f.write(config)

        result.update({
            "success": True,
            "filename": filepath,
            "message": f"OK — {filename}",
        })
        log("✓ Respaldo por consola completado.", "success")

    except Exception as e:
        result["message"] = str(e)
        log(f"✗ Error en respaldo serial: {e}", "error")
    finally:
        try:
            if ser and ser.is_open:
                ser.close()
                log("Puerto serial cerrado.", "info")
        except Exception:
            pass

    return result


def backup_device(host: str, username: str, password: str,
                  secret: str = "", output_dir: str = ".",
                  vendor: str = "Cisco",
                  extra_commands: list = None,
                  log_callback=None, timeout: int = 30,
                  cancel_event=None) -> dict:
    """
    Punto de entrada unificado de respaldo.
    Despacha al manejador adecuado según el vendor seleccionado.
    """
    def log(msg, level="info"):
        if log_callback:
            log_callback(host, msg, level)

    result = {"success": False, "host": host, "filename": "",
              "message": "", "vendor": vendor}

    if vendor not in VENDORS:
        result["message"] = f"Vendor desconocido: {vendor}"
        log(result["message"], "error")
        return result

    vendor_profile = VENDORS[vendor]
    extra_commands = extra_commands or []

    log(f"Iniciando conexión [{vendor}]...", "info")

    if vendor == "Cisco":
        config, ok, err, cmd_results = backup_device_cisco(
            host, username, password, secret, timeout,
            extra_commands, vendor_profile, log,
            cancel_event=cancel_event,
        )
    elif vendor == "Extreme":
        config, ok, err, cmd_results = backup_device_extreme(
            host, username, password, secret, timeout,
            extra_commands, vendor_profile, log,
            cancel_event=cancel_event,
        )
    else:
        result["message"] = f"Vendor {vendor} no implementado."
        log(result["message"], "error")
        return result

    # Anexar tracking de comandos al resultado
    result["cmd_results"] = cmd_results

    if not ok:
        result["message"] = err
        log(f"✗ Error total: {err}", "error")
        return result

    # =========================================================
    # 💾 GUARDADO
    # =========================================================
    try:
        hostname = host
        # Cisco: 'hostname XYZ'   |   Extreme: '* SwitchName.1 #' o 'configure snmp sysName "XYZ"'
        if vendor == "Cisco":
            m = re.search(r"hostname\s+(\S+)", config)
            if m:
                hostname = m.group(1)
        else:
            # EXOS: 'configure snmp sysname "X"' o ' SysName: X'
            m = (re.search(r'configure snmp sysname\s+"([^"]+)"', config, re.I) or
                 re.search(r'sysname\s+"?([^"\n\r]+)"?', config, re.I))
            if m:
                hostname = m.group(1).strip().split()[0]

        timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
        vtag = vendor.lower()
        filename = f"{hostname}_{host.replace('.', '_')}_{vtag}_{timestamp}.txt"
        filepath = os.path.join(output_dir, filename)

        os.makedirs(output_dir, exist_ok=True)

        # Cabecera del archivo
        header = (
            f"! ============================================================\n"
            f"!  Backup de configuración\n"
            f"!  Vendor    : {vendor}\n"
            f"!  Host      : {host}\n"
            f"!  Hostname  : {hostname}\n"
            f"!  Fecha     : {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
            f"!  Generado  : Multi-Vendor Backup Tool v1.8\n"
            f"! ============================================================\n\n"
        )

        with open(filepath, "w", encoding="utf-8") as f:
            f.write(header)
            f.write(config)

        result.update({
            "success": True,
            "filename": filepath,
            "message": f"OK — {filename}"
        })

    except Exception as e:
        result["message"] = str(e)

    return result


# ─────────────────────────────────────────────────────────────
#  WIDGETS PERSONALIZADOS
# ─────────────────────────────────────────────────────────────
class StyledEntry(tk.Frame):
    def __init__(self, parent, placeholder="", show="", width=28, **kw):
        super().__init__(parent, bg=BG_INPUT,
                         highlightbackground=BORDER,
                         highlightthickness=1, **kw)
        self._ph = placeholder
        self._show = show
        self._active = False

        self.entry = tk.Entry(
            self, bg=BG_INPUT, fg=TEXT_MUTED,
            insertbackground=ACCENT_BLUE,
            relief="flat", font=FONT_MONO,
            width=width, bd=4,
            highlightthickness=0
        )
        self.entry.pack(fill="x")
        self.entry.insert(0, placeholder)
        self.entry.bind("<FocusIn>",  self._on_focus)
        self.entry.bind("<FocusOut>", self._off_focus)

    def _on_focus(self, _):
        if not self._active:
            self.entry.delete(0, "end")
            self.entry.config(fg=TEXT_PRIMARY, show=self._show)
            self._active = True
        self.config(highlightbackground=ACCENT_BLUE)

    def _off_focus(self, _):
        if not self.entry.get():
            self.entry.config(fg=TEXT_MUTED, show="")
            self.entry.insert(0, self._ph)
            self._active = False
        self.config(highlightbackground=BORDER)

    def get(self):
        # Usamos el flag _active (que se activa al enfocar o al hacer set())
        # en lugar de comparar contra el placeholder.  Antes, si el usuario
        # escribía literalmente el mismo texto del placeholder (p.ej. "admin"),
        # el método devolvía "" y la app pensaba que estaba vacío.
        if not self._active:
            return ""
        val = self.entry.get()
        # Por compatibilidad: si por alguna razón quedó el placeholder
        # exacto y _active=False fue forzado, también devolvemos "".
        return "" if (val == self._ph and not self._active) else val

    def set(self, val):
        self.entry.delete(0, "end")
        if val:
            self.entry.config(fg=TEXT_PRIMARY, show=self._show)
            self.entry.insert(0, val)
            self._active = True
        else:
            self.entry.config(fg=TEXT_MUTED, show="")
            self.entry.insert(0, self._ph)
            self._active = False


class IconButton(tk.Button):
    def __init__(self, parent, text, color=ACCENT_BLUE,
                 hover_color=None, **kw):
        self._color = color
        self._hover = hover_color or self._darken(color)
        super().__init__(
            parent, text=text, bg=color, fg=TEXT_PRIMARY,
            font=FONT_BTN, relief="flat", bd=0,
            padx=16, pady=8, cursor="hand2",
            activebackground=self._hover,
            activeforeground=TEXT_PRIMARY, **kw
        )
        self.bind("<Enter>", lambda _: self.config(bg=self._hover))
        self.bind("<Leave>", lambda _: self.config(bg=self._color))

    @staticmethod
    def _darken(hex_color):
        r = max(0, int(hex_color[1:3], 16) - 30)
        g = max(0, int(hex_color[3:5], 16) - 30)
        b = max(0, int(hex_color[5:7], 16) - 30)
        return f"#{r:02x}{g:02x}{b:02x}"


class StatusBadge(tk.Label):
    COLORS = {
        "pending"     : (TEXT_DIM,     "Pendiente"),
        "checking"    : (ACCENT_CYAN,  "⟳ Verificando"),
        "running"     : (ACCENT_ORANGE,"⟳ Conectando"),
        "success"     : (ACCENT_GREEN, "✓ OK"),
        "error"       : (ACCENT_RED,   "✗ Error"),
        "warning"     : (ACCENT_ORANGE,"⚠ Warning"),
        "unreachable" : (ACCENT_RED,   "✗ Inaccesible"),
        "invalid"     : (ACCENT_RED,   "✗ IP inválida"),
        "no_ssh"      : (ACCENT_RED,   "✗ SSH cerrado"),
        "retrying"    : (ACCENT_ORANGE,"↻ Reintento"),
    }
    def __init__(self, parent, **kw):
        super().__init__(parent, bg=BG_CARD, font=FONT_SMALL, **kw)
        self.set("pending")

    def set(self, status):
        color, label = self.COLORS.get(status, (TEXT_MUTED, status))
        self.config(fg=color, text=label)


# ─────────────────────────────────────────────────────────────
#  VENTANA PRINCIPAL
# ─────────────────────────────────────────────────────────────
class CiscoBackupApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Multi-Vendor Switch Backup Tool")
        self.configure(bg=BG_DARK)
        self.resizable(True, True)

        # ── Adaptación a pantallas pequeñas ──
        # Detectamos la resolución y dejamos un margen para barra de
        # tareas/dock.  La ventana inicial NUNCA excede el área útil.
        sw, sh = self.winfo_screenwidth(), self.winfo_screenheight()
        # Tamaño ideal
        ideal_w, ideal_h = 1180, 760
        # Tamaño efectivo (ajustado a pantalla)
        w = min(ideal_w, sw - 60)
        h = min(ideal_h, sh - 100)
        # Mínimo razonable (laptops 1366x768 con barra de tareas)
        self.minsize(min(800, sw - 40), min(500, sh - 80))
        # Centrar
        self.geometry(f"{w}x{h}+{(sw - w) // 2}+{max(0, (sh - h) // 2 - 20)}")

        self._queue   = queue.Queue()
        self._devices = []   # lista de filas: [ip_var, status_badge, vendor_var, row]
        self._running = False
        # Event compartido para señalar cancelación desde la UI a los
        # workers.  cancel_event.set() detiene el respaldo tan pronto
        # como el worker cheque entre comandos.
        self._cancel_event = threading.Event()
        # Lista de backups con comandos fallidos, listos para reintento.
        # Cada entrada es un dict con todo el contexto necesario para
        # reconectar y ejecutar SOLO los comandos que fallaron.
        # Estructura: {host, vendor, transport, commands, filename,
        # username, password, secret, timeout,
        # port, baudrate, bytesize, parity, stopbits}
        self._last_failed_backups = []
        #self._output_dir = tk.StringVar(value=os.path.join(os.path.expanduser("~"), "switch_backups"))
        self._output_dir = tk.StringVar(value=os.path.join(os.path.expanduser("~"), "Documents", "logs"))

        # Combobox style (oscuro)
        self._setup_ttk_style()

        self._build_ui()
        self._check_queue()

        if not NETMIKO_AVAILABLE:
            self._log("⚠  Netmiko NO está instalado. Instálalo con:  pip install netmiko", "warning")
            self._log("   Los respaldos por SSH no funcionarán hasta instalarlo.", "warning")
        else:
            self._log("✓  Netmiko detectado (modo SSH). Vendors: " +
                      ", ".join(VENDORS.keys()), "success")

        if not PYSERIAL_AVAILABLE:
            self._log("⚠  pyserial NO está instalado (modo Consola deshabilitado).",
                      "warning")
            self._log("   Instálalo con:  pip install pyserial", "warning")
        else:
            ports = list_serial_ports()
            if ports:
                self._log(f"✓  pyserial detectado. Puertos COM: {', '.join(ports)}",
                          "success")
            else:
                self._log("✓  pyserial detectado (sin puertos COM activos por ahora).",
                          "info")

    # ── ttk style oscuro para Combobox/Notebook ──────────────
    def _setup_ttk_style(self):
        style = ttk.Style()
        style.theme_use("default")
        # Combobox
        style.configure("Vendor.TCombobox",
                        fieldbackground=BG_INPUT,
                        background=BG_INPUT,
                        foreground=TEXT_PRIMARY,
                        arrowcolor=ACCENT_BLUE,
                        bordercolor=BORDER,
                        lightcolor=BORDER,
                        darkcolor=BORDER,
                        borderwidth=1,
                        relief="flat")
        style.map("Vendor.TCombobox",
                  fieldbackground=[("readonly", BG_INPUT)],
                  background=[("readonly", BG_INPUT)],
                  foreground=[("readonly", TEXT_PRIMARY)])
        # Notebook
        style.configure("Vendor.TNotebook", background=BG_CARD,
                        borderwidth=0, tabmargins=[2, 5, 2, 0])
        style.configure("Vendor.TNotebook.Tab",
                        background=BG_PANEL, foreground=TEXT_MUTED,
                        padding=[14, 6], font=FONT_SMALL,
                        borderwidth=0)
        style.map("Vendor.TNotebook.Tab",
                  background=[("selected", BG_CARD)],
                  foreground=[("selected", ACCENT_CYAN)],
                  expand=[("selected", [1, 1, 1, 0])])
        # Progress
        style.configure("TProgressbar", troughcolor=BG_DARK,
                        background=ACCENT_BLUE, thickness=4)

    # ── UI Principal ──────────────────────────────────────────
    def _build_ui(self):
        # ── Header ──
        hdr = tk.Frame(self, bg=BG_PANEL, pady=0)
        hdr.pack(fill="x")
        tk.Frame(hdr, bg=ACCENT_BLUE, height=3).pack(fill="x")
        inner_hdr = tk.Frame(hdr, bg=BG_PANEL, padx=24, pady=14)
        inner_hdr.pack(fill="x")

        tk.Label(inner_hdr, text="⬡ MULTI-VENDOR SWITCH BACKUP TOOL",
                 font=FONT_TITLE, bg=BG_PANEL, fg=ACCENT_BLUE).pack(side="left")
        tk.Label(inner_hdr,
                 text="Cisco IOS / IOS-XE  ·  Extreme Networks (EXOS)  |  SSH + Consola",
                 font=FONT_SMALL, bg=BG_PANEL, fg=TEXT_MUTED).pack(side="left", padx=16, pady=6)

        ver = tk.Label(inner_hdr, text="v1.8", font=FONT_SMALL,
                       bg=ACCENT_BLUE, fg="#fff", padx=8, pady=3)
        ver.pack(side="right")

        # ── Cuerpo principal scrollable ──
        # Envolvemos toda la zona izquierda+derecha en un Canvas + Scrollbar
        # para que en pantallas chicas (laptops 1366x768) el usuario pueda
        # hacer scroll y ver todos los elementos sin recortes.
        outer = tk.Frame(self, bg=BG_DARK)
        outer.pack(fill="both", expand=True)

        body_canvas = tk.Canvas(outer, bg=BG_DARK, highlightthickness=0)
        body_scroll = ttk.Scrollbar(outer, orient="vertical",
                                    command=body_canvas.yview)
        body_canvas.configure(yscrollcommand=body_scroll.set)

        body_scroll.pack(side="right", fill="y")
        body_canvas.pack(side="left", fill="both", expand=True)

        body = tk.Frame(body_canvas, bg=BG_DARK)
        body_window = body_canvas.create_window(
            (0, 0), window=body, anchor="nw"
        )

        # Padding interno del body
        body.configure(padx=16, pady=12)

        # Actualizar scrollregion cuando cambie el tamaño del body
        def _on_body_configure(event):
            body_canvas.configure(scrollregion=body_canvas.bbox("all"))
        body.bind("<Configure>", _on_body_configure)

        # Hacer que el body se estire al ancho del canvas
        def _on_canvas_resize(event):
            body_canvas.itemconfig(body_window, width=event.width)
        body_canvas.bind("<Configure>", _on_canvas_resize)

        # Scroll con rueda del mouse (Windows / Mac / Linux)
        def _on_mousewheel(event):
            # Si el mouse está sobre el log (que tiene su propio scroll)
            # no robamos el evento.
            widget = self.winfo_containing(event.x_root, event.y_root)
            try:
                w = widget
                while w is not None:
                    if isinstance(w, scrolledtext.ScrolledText):
                        return  # no robamos scroll del log/textbox
                    w = w.master
            except Exception:
                pass
            body_canvas.yview_scroll(int(-1 * (event.delta / 120)), "units")
        body_canvas.bind_all("<MouseWheel>", _on_mousewheel)
        # Linux usa Button-4/5
        body_canvas.bind_all("<Button-4>",
                             lambda e: body_canvas.yview_scroll(-1, "units"))
        body_canvas.bind_all("<Button-5>",
                             lambda e: body_canvas.yview_scroll(1, "units"))

        left  = tk.Frame(body, bg=BG_DARK)
        right = tk.Frame(body, bg=BG_DARK)
        left.pack(side="left", fill="both", expand=False, padx=(0, 8))
        right.pack(side="left", fill="both", expand=True)

        self._build_credentials(left)
        self._build_devices_panel(left)
        self._build_output_dir(left)
        self._build_actions(left)

        # Panel derecho: comandos personalizados + log
        self._build_commands_panel(right)
        self._build_log_panel(right)

    # ── Panel credenciales ────────────────────────────────────
    def _build_credentials(self, parent):
        card = tk.Frame(parent, bg=BG_CARD,
                        highlightbackground=BORDER, highlightthickness=1)
        card.pack(fill="x", pady=(0, 8))
        tk.Frame(card, bg=ACCENT_BLUE, height=2).pack(fill="x")

        tk.Label(card, text="  CREDENCIALES SSH", font=FONT_HEADING,
                 bg=BG_CARD, fg=TEXT_PRIMARY, pady=8).pack(anchor="w")

        fields = tk.Frame(card, bg=BG_CARD, padx=12, pady=4)
        fields.pack(fill="x")

        def row(label, widget_creator):
            f = tk.Frame(fields, bg=BG_CARD)
            f.pack(fill="x", pady=3)
            tk.Label(f, text=label, font=FONT_LABEL,
                     bg=BG_CARD, fg=TEXT_MUTED, width=14, anchor="w").pack(side="left")
            w = widget_creator(f)
            w.pack(side="left", fill="x", expand=True)
            return w

        self.e_user    = row("Usuario SSH :", lambda p: StyledEntry(p, "admin", width=22))
        self.e_pass    = row("Password    :", lambda p: StyledEntry(p, "••••••", show="•", width=22))
        self.e_enable  = row("Enable Pwd  :", lambda p: StyledEntry(p, "(opcional)", show="•", width=22))
        self.e_timeout = row("Timeout (s) :", lambda p: StyledEntry(p, "30", width=22))

        # Vendor por defecto (se aplica al crear filas nuevas)
        f = tk.Frame(fields, bg=BG_CARD)
        f.pack(fill="x", pady=3)
        tk.Label(f, text="Vendor default:", font=FONT_LABEL,
                 bg=BG_CARD, fg=TEXT_MUTED, width=14, anchor="w").pack(side="left")
        # Default vendor: Extreme (cambia aquí si lo prefieres en Cisco)
        self.default_vendor = tk.StringVar(value="Extreme")
        cb = ttk.Combobox(f, textvariable=self.default_vendor,
                          values=list(VENDORS.keys()),
                          state="readonly", width=12,
                          style="Vendor.TCombobox")
        cb.pack(side="left", padx=(0, 4))

        tk.Frame(card, bg=BG_DARK, height=8).pack()

    # ── Panel dispositivos (TABS: SSH / Consola) ──────────────
    def _build_devices_panel(self, parent):
        card = tk.Frame(parent, bg=BG_CARD,
                        highlightbackground=BORDER, highlightthickness=1)
        card.pack(fill="both", expand=True, pady=(0, 8))
        tk.Frame(card, bg=ACCENT_CYAN, height=2).pack(fill="x")

        hdr = tk.Frame(card, bg=BG_CARD, padx=12, pady=8)
        hdr.pack(fill="x")
        tk.Label(hdr, text="  DISPOSITIVOS", font=FONT_HEADING,
                 bg=BG_CARD, fg=TEXT_PRIMARY).pack(side="left")

        # Notebook con dos modos de transporte
        self._transport_nb = ttk.Notebook(card, style="Vendor.TNotebook")
        self._transport_nb.pack(fill="both", expand=True, padx=6, pady=(0, 6))

        # Tab SSH (modo batch)
        ssh_tab = tk.Frame(self._transport_nb, bg=BG_CARD)
        self._transport_nb.add(ssh_tab, text="  🌐  SSH (Batch)  ")
        self._build_ssh_tab(ssh_tab)

        # Tab Consola (modo uno-a-uno)
        con_tab = tk.Frame(self._transport_nb, bg=BG_CARD)
        self._transport_nb.add(con_tab, text="  🔌  Consola (Serial)  ")
        self._build_console_tab(con_tab)

    def _active_transport(self) -> str:
        """Devuelve 'ssh' o 'serial' según el tab activo."""
        try:
            idx = self._transport_nb.index("current")
            return "ssh" if idx == 0 else "serial"
        except Exception:
            return "ssh"

    # ── Tab SSH (lista batch) ─────────────────────────────────
    def _build_ssh_tab(self, parent):
        # Barra de acciones de la lista
        bar = tk.Frame(parent, bg=BG_CARD, padx=6, pady=6)
        bar.pack(fill="x")

        add_btn = IconButton(bar, "+ Agregar", color="#1a3a1a",
                             hover_color="#1f5c1f",
                             command=self._add_device_row)
        add_btn.config(font=FONT_SMALL, padx=8, pady=4)
        add_btn.pack(side="right")

        clr_btn = IconButton(bar, "✕ Limpiar", color="#3a1a1a",
                             hover_color="#5c1f1f",
                             command=self._clear_devices)
        clr_btn.config(font=FONT_SMALL, padx=8, pady=4)
        clr_btn.pack(side="right", padx=(0, 6))

        # Cabecera columnas
        col_hdr = tk.Frame(parent, bg=BG_PANEL, padx=12, pady=4)
        col_hdr.pack(fill="x")
        for txt, w in [("  IP / Hostname", 18), ("Vendor", 10),
                       ("Estado", 12), ("", 4)]:
            tk.Label(col_hdr, text=txt, font=FONT_SMALL,
                     bg=BG_PANEL, fg=TEXT_DIM, width=w, anchor="w").pack(side="left")

        # Scroll container
        canvas_frame = tk.Frame(parent, bg=BG_CARD)
        canvas_frame.pack(fill="both", expand=True, padx=4, pady=4)

        self._canvas = tk.Canvas(canvas_frame, bg=BG_CARD,
                                 highlightthickness=0, height=180)
        scrollbar = ttk.Scrollbar(canvas_frame, orient="vertical",
                                  command=self._canvas.yview)
        self._canvas.configure(yscrollcommand=scrollbar.set)
        scrollbar.pack(side="right", fill="y")
        self._canvas.pack(side="left", fill="both", expand=True)

        self._devices_frame = tk.Frame(self._canvas, bg=BG_CARD)
        self._canvas_window = self._canvas.create_window(
            (0, 0), window=self._devices_frame, anchor="nw", width=0)
        self._devices_frame.bind("<Configure>", self._on_devices_resize)
        self._canvas.bind("<Configure>", self._on_canvas_resize)

        # Importar desde archivo
        imp = tk.Frame(parent, bg=BG_CARD, padx=12, pady=6)
        imp.pack(fill="x")
        tk.Label(imp, text="Importar IPs desde .txt (formato: IP[,vendor] por línea):",
                 font=FONT_SMALL, bg=BG_CARD, fg=TEXT_MUTED).pack(side="left")
        IconButton(imp, "📂 Importar", color=BG_INPUT,
                   command=self._import_from_file).config(
                       font=FONT_SMALL, padx=6, pady=3)
        for w in imp.winfo_children():
            if isinstance(w, tk.Button):
                w.pack(side="left", padx=8)

        # agregar 3 filas por defecto
        for _ in range(3):
            self._add_device_row()

    # ── Tab Consola (uno-a-uno) ───────────────────────────────
    def _build_console_tab(self, parent):
        # Aviso si pyserial no está
        if not PYSERIAL_AVAILABLE:
            warn = tk.Label(
                parent,
                text=("⚠  pyserial NO está instalado.\n"
                      "Para usar el modo Consola ejecuta:\n"
                      "       pip install pyserial"),
                font=FONT_MONO, bg=BG_CARD, fg=ACCENT_ORANGE,
                justify="left", padx=14, pady=20
            )
            warn.pack(anchor="w")

        wrap = tk.Frame(parent, bg=BG_CARD, padx=12, pady=10)
        wrap.pack(fill="both", expand=True)

        def field_row(label):
            f = tk.Frame(wrap, bg=BG_CARD)
            f.pack(fill="x", pady=4)
            tk.Label(f, text=label, font=FONT_LABEL, bg=BG_CARD,
                     fg=TEXT_MUTED, width=16, anchor="w").pack(side="left")
            return f

        # Puerto COM
        f = field_row("Puerto COM    :")
        self._serial_port = tk.StringVar()
        ports = list_serial_ports()
        self._serial_port_cb = ttk.Combobox(
            f, textvariable=self._serial_port,
            values=ports if ports else ["(sin puertos)"],
            state="readonly", width=18, style="Vendor.TCombobox"
        )
        if ports:
            self._serial_port.set(ports[0])
        else:
            self._serial_port.set("(sin puertos)")
        self._serial_port_cb.pack(side="left")
        IconButton(f, "↻ Refrescar", color=BG_INPUT,
                   command=self._refresh_serial_ports).config(
                       font=FONT_SMALL, padx=6, pady=3)
        for w in f.winfo_children():
            if isinstance(w, tk.Button):
                w.pack(side="left", padx=(8, 0))

        # Baud rate
        f = field_row("Baudrate      :")
        self._serial_baud = tk.StringVar(value="9600")
        ttk.Combobox(
            f, textvariable=self._serial_baud,
            values=["1200", "2400", "4800", "9600", "19200",
                    "38400", "57600", "115200"],
            state="readonly", width=10, style="Vendor.TCombobox"
        ).pack(side="left")

        # Data bits
        f = field_row("Data bits     :")
        self._serial_bytesize = tk.StringVar(value="8")
        ttk.Combobox(
            f, textvariable=self._serial_bytesize,
            values=["7", "8"], state="readonly", width=4,
            style="Vendor.TCombobox"
        ).pack(side="left")

        # Parity
        f = field_row("Parity        :")
        self._serial_parity = tk.StringVar(value="N")
        ttk.Combobox(
            f, textvariable=self._serial_parity,
            values=["N", "E", "O"], state="readonly", width=4,
            style="Vendor.TCombobox"
        ).pack(side="left")
        tk.Label(f, text=" (N=None, E=Even, O=Odd)", font=FONT_SMALL,
                 bg=BG_CARD, fg=TEXT_DIM).pack(side="left", padx=6)

        # Stop bits
        f = field_row("Stop bits     :")
        self._serial_stopbits = tk.StringVar(value="1")
        ttk.Combobox(
            f, textvariable=self._serial_stopbits,
            values=["1", "2"], state="readonly", width=4,
            style="Vendor.TCombobox"
        ).pack(side="left")

        # Vendor
        f = field_row("Vendor        :")
        self._serial_vendor = tk.StringVar(value="Extreme")
        ttk.Combobox(
            f, textvariable=self._serial_vendor,
            values=list(VENDORS.keys()), state="readonly", width=12,
            style="Vendor.TCombobox"
        ).pack(side="left")

        # Estado
        f = field_row("Estado        :")
        self._serial_badge = StatusBadge(f, width=18)
        self._serial_badge.pack(side="left", padx=2)

        # Nota
        tk.Frame(wrap, bg=BORDER, height=1).pack(fill="x", pady=(10, 6))
        tk.Label(
            wrap,
            text=("Modo Consola: un equipo a la vez.\n"
                  "Asegúrate de tener el cable USB-Serial conectado y\n"
                  "PuTTY/otro programa CERRADO (el puerto debe estar libre).\n"
                  "Si el equipo no muestra el prompt, presiona Enter en la\n"
                  "consola antes de iniciar el respaldo."),
            font=FONT_SMALL, bg=BG_CARD, fg=TEXT_MUTED,
            justify="left"
        ).pack(anchor="w", pady=4)

    def _refresh_serial_ports(self):
        ports = list_serial_ports()
        if not ports:
            self._serial_port_cb.config(values=["(sin puertos)"])
            self._serial_port.set("(sin puertos)")
            self._log("No se detectaron puertos COM disponibles.", "warning")
            return
        self._serial_port_cb.config(values=ports)
        if self._serial_port.get() not in ports:
            self._serial_port.set(ports[0])
        self._log(f"Puertos detectados: {', '.join(ports)}", "info")

    def _on_devices_resize(self, _):
        self._canvas.configure(scrollregion=self._canvas.bbox("all"))

    def _on_canvas_resize(self, event):
        self._canvas.itemconfig(self._canvas_window, width=event.width)

    def _add_device_row(self, ip="", vendor=None):
        row_idx = len(self._devices)
        row = tk.Frame(self._devices_frame, bg=BG_CARD, pady=2)
        row.pack(fill="x", padx=4)

        # número
        tk.Label(row, text=f"{row_idx+1:02d}", font=FONT_SMALL,
                 bg=BG_CARD, fg=TEXT_DIM, width=3).pack(side="left")

        ip_entry = StyledEntry(row, placeholder="192.168.1.1", width=16)
        if ip:
            ip_entry.set(ip)
        ip_entry.pack(side="left", padx=(2, 8))

        vendor_var = tk.StringVar(value=vendor or self.default_vendor.get())
        vendor_cb = ttk.Combobox(row, textvariable=vendor_var,
                                 values=list(VENDORS.keys()),
                                 state="readonly", width=9,
                                 style="Vendor.TCombobox")
        vendor_cb.pack(side="left", padx=(0, 8))

        badge = StatusBadge(row, width=12)
        badge.pack(side="left", padx=4)

        del_btn = tk.Button(row, text="✕", font=FONT_SMALL,
                            bg=BG_CARD, fg=TEXT_DIM,
                            relief="flat", cursor="hand2",
                            command=lambda r=row, d=ip_entry: self._remove_row(r, d))
        del_btn.pack(side="left", padx=4)

        self._devices.append((ip_entry, badge, vendor_var, row))
        self._canvas.configure(scrollregion=self._canvas.bbox("all"))

    def _remove_row(self, row_frame, ip_entry):
        row_frame.destroy()
        self._devices = [d for d in self._devices if d[0] is not ip_entry]

    def _clear_devices(self):
        for entry, badge, vendor_var, row in self._devices:
            row.destroy()
        self._devices.clear()

    def _import_from_file(self):
        path = filedialog.askopenfilename(
            title="Seleccionar archivo de IPs",
            filetypes=[("Text files", "*.txt"), ("All files", "*.*")]
        )
        if not path:
            return
        try:
            count = 0
            with open(path) as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith("#"):
                        continue
                    # formato:  IP   ó   IP,Vendor   ó   IP;Vendor
                    parts = re.split(r"[,;\s]+", line, maxsplit=1)
                    ip = parts[0]
                    v = None
                    if len(parts) > 1:
                        v_in = parts[1].strip().capitalize()
                        if v_in in VENDORS:
                            v = v_in
                    self._add_device_row(ip, v)
                    count += 1
            self._log(f"✓ Importadas {count} entradas desde {os.path.basename(path)}", "success")
        except Exception as e:
            self._log(f"Error importando archivo: {e}", "error")

    # ── Directorio de salida ──────────────────────────────────
    def _build_output_dir(self, parent):
        card = tk.Frame(parent, bg=BG_CARD,
                        highlightbackground=BORDER, highlightthickness=1)
        card.pack(fill="x", pady=(0, 8))
        tk.Frame(card, bg=ACCENT_GREEN, height=2).pack(fill="x")

        f = tk.Frame(card, bg=BG_CARD, padx=12, pady=10)
        f.pack(fill="x")
        tk.Label(f, text="  CARPETA DE RESPALDOS", font=FONT_HEADING,
                 bg=BG_CARD, fg=TEXT_PRIMARY).pack(anchor="w")
        f2 = tk.Frame(f, bg=BG_CARD, pady=4)
        f2.pack(fill="x")
        dir_entry = tk.Entry(f2, textvariable=self._output_dir,
                             bg=BG_INPUT, fg=TEXT_PRIMARY,
                             font=FONT_MONO, relief="flat",
                             insertbackground=ACCENT_BLUE, bd=4)
        dir_entry.pack(side="left", fill="x", expand=True)
        IconButton(f2, "📁", color=BG_INPUT,
                   command=self._choose_dir).pack(side="left", padx=(6, 0))

    def _choose_dir(self):
        d = filedialog.askdirectory(title="Carpeta de respaldos")
        if d:
            self._output_dir.set(d)

    # ── Botones de acción ─────────────────────────────────────
    def _build_actions(self, parent):
        f = tk.Frame(parent, bg=BG_DARK, pady=6)
        f.pack(fill="x")

        self.btn_start = IconButton(
            f, "▶  INICIAR RESPALDOS",
            color=ACCENT_BLUE,
            command=self._start_backup
        )
        self.btn_start.pack(fill="x", pady=(0, 6))

        row2 = tk.Frame(f, bg=BG_DARK)
        row2.pack(fill="x")

        IconButton(row2, "🔄  Resetear Estados",
                   color=BG_INPUT,
                   command=self._reset_statuses).pack(side="left", fill="x",
                                                       expand=True, padx=(0, 4))
        IconButton(row2, "📂  Abrir Carpeta",
                   color=BG_INPUT,
                   command=self._open_output_dir).pack(side="left", fill="x",
                                                        expand=True, padx=(4, 0))

        row3 = tk.Frame(f, bg=BG_DARK)
        row3.pack(fill="x", pady=(6, 0))
        IconButton(row3, "ℹ   Acerca de",
                   color="#1a1a2e",
                   hover_color="#16213e",
                   command=self._show_about).pack(fill="x")

        # ── Botón Reintentar Fallidos (dinámico) ──
        row_retry = tk.Frame(f, bg=BG_DARK)
        row_retry.pack(fill="x", pady=(6, 0))
        self.btn_retry = IconButton(
            row_retry, "↻  Reintentar Fallidos (0)",
            color="#3a2a10",
            hover_color="#5c4515",
            command=self._start_retry_backup,
        )
        self.btn_retry.pack(fill="x")
        # Arranca deshabilitado — sólo se activa cuando hay algo
        self.btn_retry.config(state="disabled")

        # ── Selector de tema (skin) ──
        skin_row = tk.Frame(f, bg=BG_DARK, pady=4)
        skin_row.pack(fill="x", pady=(8, 0))
        tk.Label(skin_row, text="🎨 Tema:", font=FONT_SMALL,
                 bg=BG_DARK, fg=TEXT_MUTED).pack(side="left", padx=(2, 6))
        self._skin_var = tk.StringVar(value=_CURRENT_SKIN_NAME)
        skin_cb = ttk.Combobox(
            skin_row, textvariable=self._skin_var,
            values=list(SKINS.keys()),
            state="readonly", width=16,
            style="Vendor.TCombobox"
        )
        skin_cb.pack(side="left", fill="x", expand=True)
        skin_cb.bind("<<ComboboxSelected>>", self._on_skin_change)

        # ── Botón SALIR (cierra la aplicación) ──
        row5 = tk.Frame(f, bg=BG_DARK)
        row5.pack(fill="x", pady=(6, 0))
        IconButton(row5, "✕  SALIR",
                   color="#5c1f1f",
                   hover_color=ACCENT_RED,
                   command=self._exit_app).pack(fill="x")

        # También enlazar la X de la ventana al mismo handler
        self.protocol("WM_DELETE_WINDOW", self._exit_app)

    def _on_skin_change(self, _evt=None):
        """Cuando el usuario cambia el skin desde el dropdown."""
        new_skin = self._skin_var.get()
        if new_skin == _CURRENT_SKIN_NAME:
            return
        # Persistir preferencia
        _save_skin_pref(new_skin)
        # Confirmar reinicio (los widgets Tkinter capturan el color al
        # crearse, así que la única forma limpia de aplicar es reiniciar)
        ok = messagebox.askyesno(
            "Cambiar tema",
            f"Tema seleccionado: {new_skin}\n\n"
            "Se aplicará al reiniciar la herramienta.\n"
            "¿Reiniciar ahora?\n\n"
            "(Si dices No, se aplicará la próxima vez que la abras.)"
        )
        if not ok:
            self._log(f"Tema '{new_skin}' guardado — se aplicará en el próximo arranque.",
                      "info")
            return
        self._log(f"Reiniciando con tema '{new_skin}'...", "info")
        # Reinicio limpio: reemplazar el proceso actual
        try:
            self.destroy()
        except Exception:
            pass
        try:
            python = sys.executable
            os.execl(python, python, *sys.argv)
        except Exception as e:
            messagebox.showerror(
                "No se pudo reiniciar automáticamente",
                f"{e}\n\nCierra y vuelve a abrir la herramienta manualmente "
                f"para ver el nuevo tema."
            )
            sys.exit(0)

    def _exit_app(self):
        """Cierra la aplicación de forma controlada."""
        # Si hay un respaldo en curso, pedir confirmación
        if self._running:
            ok = messagebox.askyesno(
                "Respaldo en curso",
                "Hay un respaldo en ejecución.\n"
                "¿Estás seguro de que quieres salir?\n\n"
                "Se interrumpirá la operación actual."
            )
            if not ok:
                return
            self._running = False

        # Cierre limpio
        try:
            self.destroy()
        except Exception:
            pass
        # Asegurar que el proceso termina (por si quedó algún hilo daemon)
        sys.exit(0)

    # ── Panel de comandos personalizados (dropdown + 1 textbox) ──
    def _build_commands_panel(self, parent):
        """
        En vez de un tab por vendor, usamos un único textbox y un
        dropdown que cambia qué vendor estamos editando.  El contenido
        se guarda por vendor en self._cmd_storage para que al cambiar
        de selección no se pierda lo escrito.  Así, agregar más vendors
        a futuro NO satura la UI — sólo amplía la lista del dropdown.
        """
        card = tk.Frame(parent, bg=BG_CARD,
                        highlightbackground=BORDER, highlightthickness=1)
        card.pack(fill="x", pady=(0, 8))
        tk.Frame(card, bg=ACCENT_PURPLE, height=2).pack(fill="x")

        hdr = tk.Frame(card, bg=BG_CARD, padx=12, pady=8)
        hdr.pack(fill="x")

        # Lado izquierdo: títulos
        hdr_left = tk.Frame(hdr, bg=BG_CARD)
        hdr_left.pack(side="left", fill="x", expand=True)
        tk.Label(hdr_left, text="  COMANDOS PERSONALIZADOS",
                 font=FONT_HEADING, bg=BG_CARD, fg=TEXT_PRIMARY).pack(side="left")
        tk.Label(hdr_left, text="(uno por línea — su salida se anexa al respaldo)",
                 font=FONT_SMALL, bg=BG_CARD, fg=TEXT_DIM).pack(side="left", padx=8)

        # Lado derecho: botones APILADOS VERTICALMENTE
        hdr_right = tk.Frame(hdr, bg=BG_CARD)
        hdr_right.pack(side="right")

        btn_default = IconButton(hdr_right, "↺ Default", color=BG_INPUT,
                                  command=self._reset_extra_commands)
        btn_default.config(font=FONT_SMALL, padx=10, pady=3)
        btn_default.pack(side="top", fill="x", pady=(0, 2))

        btn_vaciar = IconButton(hdr_right, "✕ Vaciar", color=BG_INPUT,
                                command=self._clear_extra_commands)
        btn_vaciar.config(font=FONT_SMALL, padx=10, pady=3)
        btn_vaciar.pack(side="top", fill="x", pady=(2, 0))

        # ── Storage por vendor (texto crudo, no parseado) ──
        self._cmd_storage = {
            v: profile["extra_commands_default"]
            for v, profile in VENDORS.items()
        }
        # Default del tab de comandos personalizados: Extreme si existe,
        # si no el primero del dict.
        _default_cmd_vendor = "Extreme" if "Extreme" in VENDORS else list(VENDORS.keys())[0]
        self._cmd_current = tk.StringVar(value=_default_cmd_vendor)

        # ── Selector de vendor a editar ──
        sel = tk.Frame(card, bg=BG_CARD, padx=12, pady=4)
        sel.pack(fill="x")
        tk.Label(sel, text="Editando comandos para vendor:",
                 font=FONT_LABEL, bg=BG_CARD, fg=TEXT_MUTED).pack(side="left")
        self._cmd_vendor_cb = ttk.Combobox(
            sel, textvariable=self._cmd_current,
            values=list(VENDORS.keys()),
            state="readonly", width=14, style="Vendor.TCombobox"
        )
        self._cmd_vendor_cb.pack(side="left", padx=(8, 0))
        self._cmd_vendor_cb.bind("<<ComboboxSelected>>", self._on_cmd_vendor_change)

        # ── Info de comandos por defecto del vendor activo ──
        self._cmd_info_label = tk.Label(
            card, text="", font=FONT_SMALL, bg=BG_CARD,
            fg=TEXT_MUTED, justify="left", padx=14, pady=6
        )
        self._cmd_info_label.pack(anchor="w")

        # ── Único textbox ──
        self._cmd_textbox = scrolledtext.ScrolledText(
            card, bg=BG_INPUT, fg=TEXT_PRIMARY,
            font=FONT_MONO, relief="flat", bd=0,
            insertbackground=ACCENT_BLUE, wrap="word",
            height=8, padx=8, pady=6
        )
        self._cmd_textbox.pack(fill="both", expand=True, padx=8, pady=(0, 8))

        # Cargar el contenido inicial (primer vendor)
        self._load_vendor_into_textbox(self._cmd_current.get())

    def _on_cmd_vendor_change(self, _evt=None):
        """Al cambiar de vendor en el dropdown: guardar lo escrito,
        cargar el del nuevo vendor."""
        # 1) Guardar texto actual antes de cambiar
        #    El vendor "anterior" es el que NO coincide con el seleccionado;
        #    pero como Combobox ya cambió, usamos un truco: guardamos
        #    siempre el contenido visible bajo *todos* los vendors que
        #    aún coincidan en storage; aquí más simple: persistimos
        #    antes en _sync_textbox_to_storage() al pedir comandos.
        #    Como ya cambió, basta con persistir el texto al vendor
        #    previo guardado en self._cmd_prev.
        prev = getattr(self, "_cmd_prev", None)
        if prev and prev in self._cmd_storage:
            self._cmd_storage[prev] = self._cmd_textbox.get("1.0", "end").rstrip("\n")
        # 2) Cargar el nuevo
        self._load_vendor_into_textbox(self._cmd_current.get())

    def _load_vendor_into_textbox(self, vendor: str):
        """Refresca el textbox con el contenido guardado del vendor."""
        profile = VENDORS[vendor]
        info = (
            f"Init   : {', '.join(profile['init_commands']) or '—'}     "
            f"Backup : {', '.join(profile['backup_commands'])}     "
            f"Cleanup: {', '.join(profile['cleanup_commands']) or '—'}"
        )
        self._cmd_info_label.config(text=info)

        self._cmd_textbox.delete("1.0", "end")
        self._cmd_textbox.insert("1.0", self._cmd_storage.get(vendor, ""))
        # Recordar este vendor para el próximo cambio (ver _on_cmd_vendor_change)
        self._cmd_prev = vendor

    def _sync_textbox_to_storage(self):
        """Persiste el texto visible al vendor actualmente seleccionado."""
        v = self._cmd_current.get()
        if v in self._cmd_storage:
            self._cmd_storage[v] = self._cmd_textbox.get("1.0", "end").rstrip("\n")

    def _reset_extra_commands(self):
        """Restaura defaults SOLO del vendor visible."""
        v = self._cmd_current.get()
        self._cmd_storage[v] = VENDORS[v]["extra_commands_default"]
        self._load_vendor_into_textbox(v)
        self._log(f"Comandos personalizados de {v} restaurados a defaults.", "info")

    def _clear_extra_commands(self):
        """Vacía SOLO el textbox del vendor visible."""
        v = self._cmd_current.get()
        self._cmd_storage[v] = ""
        self._cmd_textbox.delete("1.0", "end")
        self._log(f"Comandos personalizados de {v} vaciados.", "warning")

    def _get_extra_commands(self, vendor: str) -> list:
        """Devuelve la lista de comandos extra del vendor.
        Antes sincroniza lo visible al storage para no perder cambios
        sin commit (el usuario pudo editar sin cambiar dropdown)."""
        self._sync_textbox_to_storage()
        return _split_commands(self._cmd_storage.get(vendor, ""))

    # ── Panel de log ──────────────────────────────────────────
    def _build_log_panel(self, parent):
        card = tk.Frame(parent, bg=BG_CARD,
                        highlightbackground=BORDER, highlightthickness=1)
        card.pack(fill="both", expand=True)
        tk.Frame(card, bg=ACCENT_ORANGE, height=2).pack(fill="x")

        log_hdr = tk.Frame(card, bg=BG_CARD, padx=12, pady=8)
        log_hdr.pack(fill="x")
        tk.Label(log_hdr, text="  CONSOLA DE OPERACIONES",
                 font=FONT_HEADING, bg=BG_CARD, fg=TEXT_PRIMARY).pack(side="left")
        IconButton(log_hdr, "Limpiar", color=BG_INPUT,
                   command=self._clear_log).config(font=FONT_SMALL, padx=8, pady=3)
        for w in log_hdr.winfo_children():
            if isinstance(w, tk.Button):
                w.pack(side="right")

        self.log_box = scrolledtext.ScrolledText(
            card, bg=BG_DARK, fg=TEXT_PRIMARY,
            font=FONT_MONO, relief="flat", bd=0,
            insertbackground=ACCENT_BLUE,
            state="disabled", wrap="word",
            padx=10, pady=8
        )
        self.log_box.pack(fill="both", expand=True, padx=6, pady=(0, 6))

        # Tags de color
        self.log_box.tag_config("info",    foreground=TEXT_MUTED)
        self.log_box.tag_config("success", foreground=ACCENT_GREEN)
        self.log_box.tag_config("error",   foreground=ACCENT_RED)
        self.log_box.tag_config("warning", foreground=ACCENT_ORANGE)
        self.log_box.tag_config("host",    foreground=ACCENT_CYAN)
        self.log_box.tag_config("dim",     foreground=TEXT_DIM)

        # Barra de estado resumen
        self.status_bar = tk.Label(
            card, text="Listo.",
            font=FONT_SMALL, bg=BG_PANEL, fg=TEXT_MUTED,
            anchor="w", padx=12, pady=4
        )
        self.status_bar.pack(fill="x")

        # Progress
        self.progress = ttk.Progressbar(card, mode="determinate")
        self.progress.pack(fill="x", padx=6, pady=(0, 6))

    # ── Helpers UI ────────────────────────────────────────────
    def _log(self, message, level="info"):
        """Escribe en el log con color."""
        def _write():
            self.log_box.config(state="normal")
            ts = datetime.datetime.now().strftime("%H:%M:%S")
            self.log_box.insert("end", f"[{ts}] ", "dim")
            self.log_box.insert("end", message + "\n", level)
            self.log_box.see("end")
            self.log_box.config(state="disabled")
        self.after(0, _write)

    def _log_host(self, host, message, level="info"):
        def _write():
            self.log_box.config(state="normal")
            ts = datetime.datetime.now().strftime("%H:%M:%S")
            self.log_box.insert("end", f"[{ts}] ", "dim")
            self.log_box.insert("end", f"[{host}] ", "host")
            self.log_box.insert("end", message + "\n", level)
            self.log_box.see("end")
            self.log_box.config(state="disabled")
        self.after(0, _write)

    def _clear_log(self):
        self.log_box.config(state="normal")
        self.log_box.delete("1.0", "end")
        self.log_box.config(state="disabled")

    def _reset_statuses(self):
        # 1. Detener ejecución en curso
        self._running = False

        # 2. Limpiar cola si existe
        if hasattr(self, "_queue"):
            try:
                while not self._queue.empty():
                    self._queue.get_nowait()
            except Exception:
                pass

        # 3. Reset visual (SSH)
        for entry, badge, vendor_var, _ in self._devices:
            badge.set("pending")
        # 3b. Reset visual (Consola)
        if hasattr(self, "_serial_badge"):
            self._serial_badge.set("pending")

        # 4. Reset progreso/contadores
        self.progress.config(value=0)
        self._set_status_bar("Listo.")
        self._log("Sistema reiniciado correctamente.", "dim")

    def _open_output_dir(self):
        d = self._output_dir.get()
        os.makedirs(d, exist_ok=True)
        if sys.platform == "win32":
            os.startfile(d)
        elif sys.platform == "darwin":
            subprocess.Popen(["open", d])
        else:
            subprocess.Popen(["xdg-open", d])

    def _show_about(self):
        """Ventana modal Acerca de — créditos del autor original y del fork."""
        win = tk.Toplevel(self)
        win.title("Acerca de — Multi-Vendor Backup Tool v1.8")
        win.configure(bg=BG_DARK)
        win.resizable(False, False)
        win.grab_set()  # modal

        # Tamaño con margen para que SIEMPRE quepa el botón Cerrar al
        # fondo, incluso con las dos secciones (autor original + fork).
        w, h = 580, 760
        px = self.winfo_x() + max(0, (self.winfo_width()  - w) // 2)
        py = self.winfo_y() + max(0, (self.winfo_height() - h) // 2)
        sh = self.winfo_screenheight()
        if py + h > sh - 60:
            h = max(560, sh - 100)
            py = max(0, (sh - h) // 2)
        win.geometry(f"{w}x{h}+{px}+{py}")

        # ── Botón cerrar PRIMERO (side=bottom) para que nunca se pierda ──
        btn_frame = tk.Frame(win, bg=BG_PANEL, pady=12)
        btn_frame.pack(fill="x", side="bottom")
        close_btn = IconButton(btn_frame, "  ✕   Cerrar  ",
                               color=ACCENT_PURPLE,
                               command=win.destroy)
        close_btn.config(padx=20, pady=2, font=("Consolas", 10, "bold"))
        close_btn.pack(ipady=2, pady=2)

        # ── Franja superior ──
        tk.Frame(win, bg=ACCENT_BLUE, height=4).pack(fill="x")

        # ── Logo ASCII ──
        logo_frame = tk.Frame(win, bg=BG_DARK, pady=14)
        logo_frame.pack(fill="x")

        logo_text = (
            " ███╗   ███╗██╗   ██╗██╗  ████████╗██╗      ██╗   ██╗\n"
            " ████╗ ████║██║   ██║██║  ╚══██╔══╝██║      ██║   ██║\n"
            " ██╔████╔██║██║   ██║██║     ██║   ██║█████╗██║   ██║\n"
            " ██║╚██╔╝██║██║   ██║██║     ██║   ██║╚════╝╚██╗ ██╔╝\n"
            " ██║ ╚═╝ ██║╚██████╔╝███████╗██║   ██║       ╚████╔╝ \n"
            " ╚═╝     ╚═╝ ╚═════╝ ╚══════╝╚═╝   ╚═╝        ╚═══╝  "
        )
        tk.Label(logo_frame, text=logo_text,
                 font=("Consolas", 7, "bold"),
                 bg=BG_DARK, fg=ACCENT_BLUE,
                 justify="center").pack()

        tk.Label(logo_frame, text="MULTI-VENDOR SWITCH BACKUP TOOL",
                 font=("Consolas", 10, "bold"),
                 bg=BG_DARK, fg=ACCENT_CYAN).pack(pady=(4, 0))

        tk.Frame(win, bg=BORDER, height=1).pack(fill="x", padx=30)

        # ── Descripción + vendors + transportes ──
        desc_frame = tk.Frame(win, bg=BG_PANEL, padx=30, pady=14)
        desc_frame.pack(fill="x")
        desc = (
            "Herramienta para obtener respaldos automáticos de la\n"
            "configuración de switches vía SSH o consola serial.\n"
            "Soporta comandos personalizados por vendor."
        )
        tk.Label(desc_frame, text=desc,
                 font=("Consolas", 9),
                 bg=BG_PANEL, fg=TEXT_PRIMARY,
                 justify="center").pack()

        models_frame = tk.Frame(desc_frame, bg=BG_PANEL, pady=6)
        models_frame.pack()
        for model, color in [
            ("Cisco IOS",     ACCENT_BLUE),
            ("Cisco IOS-XE",  ACCENT_CYAN),
            ("Extreme EXOS",  ACCENT_PURPLE),
        ]:
            tk.Label(models_frame, text=f"  {model}  ",
                     font=FONT_SMALL, bg=color,
                     fg=BG_DARK, padx=6, pady=3).pack(side="left", padx=3)

        trans_frame = tk.Frame(desc_frame, bg=BG_PANEL, pady=4)
        trans_frame.pack()
        for label, color in [
            ("SSH",            ACCENT_GREEN),
            ("Consola Serial", ACCENT_ORANGE),
        ]:
            tk.Label(trans_frame, text=f"  {label}  ",
                     font=FONT_SMALL, bg=color,
                     fg=BG_DARK, padx=6, pady=3).pack(side="left", padx=3)

        tk.Frame(win, bg=BORDER, height=1).pack(fill="x", padx=30, pady=(6, 0))

        # ── AUTOR ORIGINAL ──
        orig_frame = tk.Frame(win, bg=BG_DARK, pady=10, padx=30)
        orig_frame.pack(fill="x")
        tk.Label(orig_frame, text="AUTOR ORIGINAL",
                 font=("Consolas", 8, "bold"),
                 bg=BG_DARK, fg=TEXT_DIM).pack(anchor="w")
        tk.Label(orig_frame,
                 text="Alberto Arellano A.",
                 font=("Consolas", 13, "bold"),
                 bg=BG_DARK, fg=TEXT_PRIMARY).pack(anchor="w", pady=(2, 0))
        tk.Label(orig_frame,
                 text="Cisco Switch Backup Tool — Licencia MIT",
                 font=("Consolas", 8, "italic"),
                 bg=BG_DARK, fg=TEXT_MUTED).pack(anchor="w", pady=(0, 4))

        certs_frame = tk.Frame(orig_frame, bg=BG_DARK)
        certs_frame.pack(anchor="w")
        for cert, color in [
            ("CCNA",         "#1f6feb"),
            ("CCNP",         "#388bfd"),
            ("Automation",   "#3fb950"),
            ("  IA  ",       "#1f6feb"),
            ("Cybersecurity","#d29922"),
        ]:
            tk.Label(certs_frame, text=f" {cert} ",
                     font=("Consolas", 8, "bold"),
                     bg=BG_PANEL,
                     fg=color,
                     highlightbackground=color,
                     highlightthickness=1,
                     padx=5, pady=3).pack(side="left", padx=(0, 5))

        tk.Frame(win, bg=BORDER, height=1).pack(fill="x", padx=30, pady=(8, 0))

        # ── FORK / EXTENSIÓN ──
        fork_frame = tk.Frame(win, bg=BG_DARK, pady=10, padx=30)
        fork_frame.pack(fill="x")
        tk.Label(fork_frame, text="FORK / EXTENSIÓN",
                 font=("Consolas", 8, "bold"),
                 bg=BG_DARK, fg=TEXT_DIM).pack(anchor="w")
        tk.Label(fork_frame,
                 text="Alberto Ramírez",
                 font=("Consolas", 13, "bold"),
                 bg=BG_DARK, fg=ACCENT_GREEN).pack(anchor="w", pady=(2, 0))
        tk.Label(fork_frame,
                 text="Fork basado en la herramienta original — agrega:",
                 font=("Consolas", 8, "italic"),
                 bg=BG_DARK, fg=TEXT_MUTED).pack(anchor="w", pady=(0, 4))

        features = [
            "-  Soporte para switches Extreme Networks (EXOS)",
            "-  Modo de conexion por Consola Serial (USB-Serial)",
            "-  Comandos personalizados configurables por vendor",
            "-  Selector de tema (skins) con paletas intercambiables",
        ]
        for f in features:
            tk.Label(fork_frame, text=f,
                     font=("Consolas", 8),
                     bg=BG_DARK, fg=TEXT_PRIMARY,
                     anchor="w", justify="left").pack(anchor="w")

        tk.Frame(win, bg=BORDER, height=1).pack(fill="x", padx=30, pady=(10, 0))
        info_frame = tk.Frame(win, bg=BG_DARK, padx=30, pady=8)
        info_frame.pack(fill="x")
        tk.Label(info_frame,
                 text="Version 1.6 Multi-Vendor   |   SSH + Serial   |   Netmiko + Scrapli + pyserial",
                 font=FONT_SMALL, bg=BG_DARK, fg=TEXT_DIM).pack()

    def _set_status_bar(self, text):
        self.after(0, lambda: self.status_bar.config(text=text))

    def _set_progress(self, val):
        self.after(0, lambda: self.progress.config(value=val))

    # ── Lógica de respaldo ────────────────────────────────────
    def _start_backup(self):
        if self._running:
            return

        # Despachar según el tab activo (SSH batch o Consola serial)
        transport = self._active_transport()
        if transport == "serial":
            return self._start_backup_serial()

        username = self.e_user.get()
        password = self.e_pass.get()
        enable   = self.e_enable.get()
        timeout_str = self.e_timeout.get()

        if not username:
            messagebox.showwarning("Usuario faltante",
                                   "Ingresa al menos el usuario SSH.\n"
                                   "(El password puede ir vacío si el equipo "
                                   "lo permite.)")
            return
        # Password puede ir vacío: algunos equipos legacy lo aceptan así.

        try:
            timeout = int(timeout_str) if timeout_str else 30
        except ValueError:
            timeout = 30

        devices_to_backup = []
        for entry, badge, vendor_var, _ in self._devices:
            ip = entry.get().strip()
            if ip:
                devices_to_backup.append((ip, entry, badge, vendor_var.get()))

        if not devices_to_backup:
            messagebox.showwarning("Sin dispositivos",
                                   "Agrega al menos una IP de dispositivo.")
            return

        # Snapshot de comandos extra por vendor (uno por todos los hosts)
        extra_by_vendor = {v: self._get_extra_commands(v) for v in VENDORS}

        self._running = True
        self._cancel_event.clear()
        # El botón cambia a modo DETENER durante el respaldo
        self.btn_start.config(
            state="normal", text="⏹  DETENER",
            command=self._request_cancel,
            bg=ACCENT_RED,
        )

        vendors_in_run = sorted({v for _, _, _, v in devices_to_backup})

        self._log("=" * 60, "dim")
        self._log(f"Iniciando respaldo de {len(devices_to_backup)} dispositivo(s)...", "info")
        self._log(f"Usuario: {username}   |   Timeout: {timeout}s", "info")
        self._log(f"Vendors en esta corrida: {', '.join(vendors_in_run)}", "info")
        for v in vendors_in_run:
            n = len(extra_by_vendor[v])
            self._log(f"  · {v}: {n} comando(s) extra configurado(s)", "info")
        self._log(f"Carpeta: {self._output_dir.get()}", "info")
        self._log("=" * 60, "dim")

        total = len(devices_to_backup)
        self.progress.config(maximum=total, value=0)

        def worker():
            ok = err = skipped = 0

            # ── 1. Validar IPs duplicadas antes de empezar ──────
            all_ips = [ip for ip, _, _, _ in devices_to_backup]
            dupes   = check_duplicates(all_ips)
            if dupes:
                self._log(
                    f"⚠  IPs duplicadas detectadas: {', '.join(set(dupes))}. "
                    f"Se procesará solo la primera aparición.",
                    "warning"
                )
                seen_ips     = set()
                unique_devs  = []
                for ip, entry, badge, vendor in devices_to_backup:
                    if ip not in seen_ips:
                        unique_devs.append((ip, entry, badge, vendor))
                        seen_ips.add(ip)
                    else:
                        self.after(0, lambda b=badge: b.set("warning"))
                        self._log_host(ip, "Duplicado — omitido.", "warning")
                        skipped += 1
                devices_to_backup_final = unique_devs
            else:
                devices_to_backup_final = devices_to_backup

            total_final = len(devices_to_backup_final)
            self.progress.config(maximum=max(total_final, 1), value=0)

            for idx, (ip, entry, badge, vendor) in enumerate(devices_to_backup_final):

                if not self._running or self._cancel_event.is_set():
                    self._log("⏹ Ejecución detenida por el usuario.", "warning")
                    break

                # ── 2. Pre-check: formato IP y accesibilidad ────
                self.after(0, lambda b=badge: b.set("checking"))
                self._log_host(ip, f"[{vendor}] Verificando accesibilidad...", "info")

                check = pre_check_host(ip, ssh_port=22, tcp_timeout=3.0)

                if not check["valid"]:
                    self.after(0, lambda b=badge: b.set("invalid"))
                    self._log_host(ip, f"✗ {check['message']}", "error")
                    err += 1
                    self._set_progress(idx + 1)
                    self._set_status_bar(f"Progreso: {idx+1}/{total_final} — ✓ {ok}  ✗ {err}  ⊘ {skipped}")
                    continue

                if check["skip"]:
                    badge_state = "no_ssh" if check["reachable"] else "unreachable"
                    self.after(0, lambda b=badge, s=badge_state: b.set(s))
                    self._log_host(ip, f"✗ {check['message']}", "error")
                    err += 1
                    self._set_progress(idx + 1)
                    self._set_status_bar(f"Progreso: {idx+1}/{total_final} — ✓ {ok}  ✗ {err}  ⊘ {skipped}")
                    continue

                self._log_host(ip, f"✓ Puerto SSH/22 accesible. Iniciando conexión [{vendor}]...", "success")
                self.after(0, lambda b=badge: b.set("running"))

                # ── 3. Intento de backup con reintentos ─────────
                MAX_RETRIES = 2
                result      = None

                for attempt in range(1, MAX_RETRIES + 1):
                    if attempt > 1:
                        self.after(0, lambda b=badge: b.set("retrying"))
                        self._log_host(ip,
                            f"↻ Reintento {attempt}/{MAX_RETRIES} "
                            f"(esperando 5s)...", "warning")
                        time.sleep(5)

                    result = backup_device(
                        host=ip, username=username, password=password,
                        secret=enable, output_dir=self._output_dir.get(),
                        vendor=vendor,
                        extra_commands=extra_by_vendor.get(vendor, []),
                        log_callback=self._log_host, timeout=timeout,
                        cancel_event=self._cancel_event,
                    )

                    if result["success"]:
                        break

                    msg_lower = result["message"].lower()
                    if any(k in msg_lower for k in
                           ["autenticación", "authentication", "password", "credential"]):
                        self._log_host(ip,
                            "Error de credenciales — no se reintentará.", "error")
                        break

                # ── 4. Registrar resultado final ────────────────
                if result and result["success"]:
                    ok += 1
                    self.after(0, lambda b=badge: b.set("success"))
                    self._log_host(ip, result["message"], "success")
                else:
                    err += 1
                    self.after(0, lambda b=badge: b.set("error"))

                # Resumen de comandos extra (si el backup produjo tracking)
                if result and result.get("cmd_results"):
                    self._log_cmd_results_summary(ip, result["cmd_results"])
                    # Si hay comandos fallidos, registrar para retry
                    failed_cmds = [r["cmd"] for r in result["cmd_results"]
                                   if not r["ok"]]
                    if failed_cmds and result.get("filename"):
                        self._register_failed_backup({
                            "host": ip,
                            "vendor": vendor,
                            "transport": "ssh",
                            "commands": failed_cmds,
                            "filename": result["filename"],
                            "username": username,
                            "password": password,
                            "secret": enable,
                            "timeout": timeout,
                        })

                self._set_progress(idx + 1)
                self._set_status_bar(
                    f"Progreso: {idx+1}/{total_final} — ✓ {ok}  ✗ {err}  ⊘ {skipped}"
                )

            # ── 5. Resumen final ─────────────────────────────────
            self._log("=" * 60, "dim")
            summary = (
                f"COMPLETADO: ✓ {ok} exitosos  "
                f"✗ {err} fallidos  "
                f"⊘ {skipped} omitidos  "
                f"de {total} total."
            )
            level = "success" if err == 0 and skipped == 0 else "warning"
            self._log(summary, level)
            self._set_status_bar(
                f"Finalizado: ✓ {ok} exitosos  ✗ {err} fallidos  ⊘ {skipped} omitidos"
            )
            self.after(0, lambda: self.btn_start.config(
                state="normal", text="▶  INICIAR RESPALDOS",
                command=self._start_backup, bg=ACCENT_BLUE))
            self._running = False

        thread = threading.Thread(target=worker, daemon=True)
        thread.start()

    # ── Respaldo modo CONSOLA (uno-a-uno) ─────────────────────
    def _start_backup_serial(self):
        if not PYSERIAL_AVAILABLE:
            messagebox.showerror(
                "pyserial no instalado",
                "El modo Consola requiere pyserial.\n\n"
                "Instala con:\n    pip install pyserial"
            )
            return

        port = self._serial_port.get()
        if not port or port == "(sin puertos)":
            messagebox.showwarning(
                "Puerto COM no seleccionado",
                "Selecciona un puerto COM válido.\n"
                "Si tu cable USB-Serial no aparece, presiona ↻ Refrescar."
            )
            return

        try:
            baudrate = int(self._serial_baud.get())
            bytesize = int(self._serial_bytesize.get())
            stopbits = int(self._serial_stopbits.get())
        except ValueError:
            messagebox.showwarning("Configuración inválida",
                                   "Revisa baudrate / data bits / stop bits.")
            return
        parity = self._serial_parity.get()
        vendor = self._serial_vendor.get()

        username = self.e_user.get()
        password = self.e_pass.get()
        enable   = self.e_enable.get()

        if not username:
            messagebox.showwarning(
                "Usuario faltante",
                "Ingresa al menos el usuario.\n"
                "(El password puede ir vacío para equipos sin password.)"
            )
            return
        # Password puede ir vacío: para equipos con password en blanco
        # el código de login envía sólo Enter cuando se solicita.

        extra_commands = self._get_extra_commands(vendor)

        self._running = True
        self._cancel_event.clear()
        self.btn_start.config(
            state="normal", text="⏹  DETENER",
            command=self._request_cancel,
            bg=ACCENT_RED,
        )
        self._serial_badge.set("running")

        self._log("=" * 60, "dim")
        self._log(f"MODO CONSOLA — Puerto {port} @ {baudrate}-{bytesize}{parity}{stopbits}", "info")
        self._log(f"Vendor: {vendor}   |   Usuario: {username}", "info")
        self._log(f"Comandos extra: {len(extra_commands)}", "info")
        self._log(f"Carpeta: {self._output_dir.get()}", "info")
        self._log("=" * 60, "dim")

        def worker():
            try:
                result = backup_via_serial(
                    port=port, baudrate=baudrate,
                    username=username, password=password, secret=enable,
                    vendor=vendor, extra_commands=extra_commands,
                    output_dir=self._output_dir.get(),
                    log_callback=self._log_host,
                    bytesize=bytesize, parity=parity, stopbits=stopbits,
                    cancel_event=self._cancel_event,
                )

                if result["success"]:
                    self.after(0, lambda: self._serial_badge.set("success"))
                    self._log(f"✓ {result['message']}", "success")
                    self._set_status_bar(f"Consola: ✓ {os.path.basename(result['filename'])}")
                else:
                    self.after(0, lambda: self._serial_badge.set("error"))
                    self._log(f"✗ Falló: {result['message']}", "error")
                    self._set_status_bar(f"Consola: ✗ {result['message'][:60]}")

                # Resumen de comandos extra
                if result.get("cmd_results"):
                    self._log_cmd_results_summary(port, result["cmd_results"])
                    failed_cmds = [r["cmd"] for r in result["cmd_results"]
                                   if not r["ok"]]
                    if failed_cmds and result.get("filename"):
                        self._register_failed_backup({
                            "host": port,
                            "vendor": vendor,
                            "transport": "serial",
                            "commands": failed_cmds,
                            "filename": result["filename"],
                            "username": username,
                            "password": password,
                            "secret": enable,
                            "port": port,
                            "baudrate": baudrate,
                            "bytesize": bytesize,
                            "parity": parity,
                            "stopbits": stopbits,
                        })
            except Exception as e:
                self.after(0, lambda: self._serial_badge.set("error"))
                self._log(f"✗ Excepción inesperada: {e}", "error")
            finally:
                self.after(0, lambda: self.btn_start.config(
                    state="normal", text="▶  INICIAR RESPALDOS",
                    command=self._start_backup, bg=ACCENT_BLUE))
                self._running = False

        threading.Thread(target=worker, daemon=True).start()

    def _register_failed_backup(self, info: dict):
        """Registra un backup con comandos fallidos para posible retry.
        Reemplaza cualquier entrada previa del mismo host+transport
        (para que un retry limpio no acumule contexto viejo)."""
        key = (info.get("host"), info.get("transport"))
        # Quitar entradas viejas del mismo host+transport
        self._last_failed_backups = [
            fb for fb in self._last_failed_backups
            if (fb.get("host"), fb.get("transport")) != key
        ]
        self._last_failed_backups.append(info)
        self._update_retry_button()

    def _update_retry_button(self):
        """Refresca el label del botón con el conteo actual."""
        if not hasattr(self, "btn_retry"):
            return
        n_hosts = len(self._last_failed_backups)
        n_cmds = sum(len(fb["commands"]) for fb in self._last_failed_backups)
        if n_hosts == 0:
            self.btn_retry.config(
                text="↻  Reintentar Fallidos (0)",
                state="disabled",
            )
        else:
            self.btn_retry.config(
                text=f"↻  Reintentar Fallidos ({n_cmds} cmd / {n_hosts} eq)",
                state="normal",
            )

    def _start_retry_backup(self):
        """Reintenta los comandos fallidos del último batch de backups."""
        if self._running:
            messagebox.showwarning("En ejecución",
                                   "Espera a que termine el respaldo actual.")
            return
        if not self._last_failed_backups:
            messagebox.showinfo("Sin fallidos",
                                "No hay comandos fallidos pendientes.")
            return

        # Construir mensaje de confirmación
        detail_lines = []
        total_cmds = 0
        for fb in self._last_failed_backups:
            n = len(fb["commands"])
            total_cmds += n
            detail_lines.append(
                f"  • {fb['host']} ({fb['vendor']}, "
                f"{fb['transport']}): {n} cmd(s)"
            )
        detail = "\n".join(detail_lines)

        ok = messagebox.askyesno(
            "Reintentar comandos fallidos",
            f"Se reintentarán {total_cmds} comando(s) en "
            f"{len(self._last_failed_backups)} equipo(s):\n\n"
            f"{detail}\n\n"
            "Los resultados se ANEXARÁN al archivo original de cada "
            "equipo, con un separador RETRY.\n\n"
            "¿Continuar?"
        )
        if not ok:
            return

        self._running = True
        self._cancel_event.clear()
        self.btn_start.config(
            state="normal", text="⏹  DETENER",
            command=self._request_cancel,
            bg=ACCENT_RED,
        )
        self.btn_retry.config(state="disabled")

        # Snapshot de la lista (para poder mutarla dentro del worker)
        pending = list(self._last_failed_backups)

        self._log("=" * 60, "dim")
        self._log(f"🔁 RETRY: reejecutando {total_cmds} comando(s) en "
                  f"{len(pending)} equipo(s)...", "info")
        self._log("=" * 60, "dim")

        def worker():
            still_failing = []
            total_hosts = len(pending)
            for idx, fb in enumerate(pending, 1):
                if self._cancel_event.is_set():
                    self._log("⏹ RETRY cancelado por el usuario.", "warning")
                    still_failing.extend(pending[idx-1:])
                    break

                host = fb["host"]
                self._log_host(host,
                    f"[RETRY {idx}/{total_hosts}] {fb['vendor']} vía "
                    f"{fb['transport']}...", "info")

                try:
                    if fb["transport"] == "ssh":
                        rr = retry_ssh_commands(
                            host=fb["host"],
                            username=fb["username"],
                            password=fb["password"],
                            secret=fb.get("secret", ""),
                            timeout=fb.get("timeout", 30),
                            vendor=fb["vendor"],
                            commands=fb["commands"],
                            append_to_file=fb["filename"],
                            log_callback=self._log_host,
                            cancel_event=self._cancel_event,
                        )
                    else:  # serial
                        rr = retry_serial_commands(
                            port=fb["port"],
                            baudrate=fb["baudrate"],
                            username=fb["username"],
                            password=fb["password"],
                            secret=fb.get("secret", ""),
                            vendor=fb["vendor"],
                            commands=fb["commands"],
                            append_to_file=fb["filename"],
                            bytesize=fb.get("bytesize", 8),
                            parity=fb.get("parity", "N"),
                            stopbits=fb.get("stopbits", 1),
                            log_callback=self._log_host,
                            cancel_event=self._cancel_event,
                        )

                    # Ver cuáles siguen fallando
                    still = [r["cmd"] for r in rr.get("cmd_results", [])
                             if not r["ok"]]
                    if still:
                        # Actualizar el registro con solo los que aún fallan
                        new_fb = dict(fb)
                        new_fb["commands"] = still
                        still_failing.append(new_fb)
                        self._log_host(host,
                            f"⚠ RETRY parcial: {len(still)} comando(s) "
                            f"aún fallando.", "warning")
                    else:
                        self._log_host(host,
                            "✓ RETRY OK: todos los comandos exitosos.",
                            "success")
                    # Log del resumen
                    if rr.get("cmd_results"):
                        self._log_cmd_results_summary(host, rr["cmd_results"])

                except Exception as e:
                    self._log_host(host,
                        f"✗ Error en RETRY: {e}", "error")
                    still_failing.append(fb)

            # Actualizar el registro global de fallidos
            self._last_failed_backups = still_failing
            self.after(0, self._update_retry_button)

            # Resumen final
            self._log("=" * 60, "dim")
            if still_failing:
                n_cmds = sum(len(f["commands"]) for f in still_failing)
                self._log(f"🔁 RETRY finalizado. Aún fallando: "
                          f"{n_cmds} comando(s) en {len(still_failing)} "
                          f"equipo(s).", "warning")
            else:
                self._log("🔁 RETRY finalizado. ✓ Todos los comandos "
                          "recuperados.", "success")

            # Restaurar botón principal
            self.after(0, lambda: self.btn_start.config(
                state="normal", text="▶  INICIAR RESPALDOS",
                command=self._start_backup, bg=ACCENT_BLUE))
            self._running = False

        threading.Thread(target=worker, daemon=True).start()

    def _request_cancel(self):
        """El usuario presionó DETENER durante un respaldo en curso."""
        if not self._running:
            return
        self._log("=" * 60, "dim")
        self._log("⏹ Cancelación solicitada. Terminando comando actual...",
                  "warning")
        self._log("=" * 60, "dim")
        self._cancel_event.set()
        self._running = False
        # Feedback visual inmediato aunque el worker aún no haya salido
        self.btn_start.config(state="disabled", text="⏳  Deteniendo...")

    def _log_cmd_results_summary(self, host: str, cmd_results: list):
        """Escribe en el log un resumen legible de la ejecución
        de los comandos extra (útil para saber qué salió bien/mal).
        Si hay fallidos, los guardamos en self._last_failed_cmds
        por si el usuario quiere reintentar solo esos."""
        if not cmd_results:
            return
        ok = [r for r in cmd_results if r["ok"]]
        fail = [r for r in cmd_results if not r["ok"]]
        self._log_host(host, "─" * 40, "dim")
        self._log_host(host,
            f"Resumen comandos extra: ✓ {len(ok)}   ✗ {len(fail)}   "
            f"de {len(cmd_results)} total.",
            "success" if not fail else "warning")
        for r in cmd_results:
            if r["ok"]:
                self._log_host(host,
                    f"  ✓ {r['cmd']:<40} {r['chars']:>6} chars  "
                    f"{r['elapsed']:>5.1f}s",
                    "success")
            else:
                self._log_host(host,
                    f"  ✗ {r['cmd']:<40} {r['error']}", "error")
        if fail:
            self._log_host(host,
                f"💡 Presiona '↻ Reintentar Fallidos' para reejecutar "
                f"solo estos {len(fail)} comando(s) y anexarlos al "
                f"archivo original.",
                "warning")

    def _check_queue(self):
        """Procesa mensajes del queue (para extensiones)."""
        try:
            while True:
                self._queue.get_nowait()
        except queue.Empty:
            pass
        self.after(100, self._check_queue)


# ─────────────────────────────────────────────────────────────
#  ENTRY POINT
# ─────────────────────────────────────────────────────────────
if __name__ == "__main__":
    # Cargar preferencia de skin guardada (si existe) ANTES de crear
    # la app — las globals de color se reemplazan en sitio y todos los
    # widgets se construirán con la paleta correcta.
    _loaded = _load_skin_pref()
    app = CiscoBackupApp()
    if _loaded != DEFAULT_SKIN:
        app._log(f"Tema activo: {_loaded}", "info")
    app.mainloop()