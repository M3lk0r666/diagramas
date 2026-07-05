<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagramas · Portal de Infraestructura de Red</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── Nav ── */
        nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between;
            height: 60px;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.1rem; color: #1e293b; text-decoration: none; }
        .nav-brand i { font-size: 1.4rem; color: #4f46e5; }
        .btn-primary {
            padding: 7px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600;
            background: #4f46e5; color: white; border: none; cursor: pointer;
            text-decoration: none; transition: background .15s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary:hover { background: #4338ca; }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
            color: white;
            padding: 80px 2rem 90px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 100px; padding: 5px 14px;
            font-size: 0.78rem; font-weight: 500; color: rgba(255,255,255,0.85);
            margin-bottom: 22px;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.25rem); font-weight: 800;
            line-height: 1.15; letter-spacing: -0.03em;
            margin-bottom: 18px;
        }
        .hero h1 span { color: #a5b4fc; }
        .hero p {
            font-size: 1.05rem; color: rgba(255,255,255,0.75);
            max-width: 560px; margin: 0 auto 36px; line-height: 1.65;
        }
        .hero-cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: white; color: #4f46e5;
            padding: 13px 28px; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700;
            text-decoration: none; border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            transition: transform .15s, box-shadow .15s;
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,0.25); }
        .btn-hero-outline {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.1); color: white;
            padding: 13px 24px; border-radius: 10px;
            font-size: 0.95rem; font-weight: 500;
            text-decoration: none; border: 1.5px solid rgba(255,255,255,0.25);
            transition: background .15s;
        }
        .btn-hero-outline:hover { background: rgba(255,255,255,0.18); }

        /* ── Stats bar ── */
        .stats-bar {
            background: white; border-bottom: 1px solid #e2e8f0;
            padding: 20px 2rem;
            display: flex; justify-content: center; gap: 60px; flex-wrap: wrap;
        }
        .stat { text-align: center; }
        .stat-icon { font-size: 1.4rem; color: #4f46e5; line-height: 1; }
        .stat-label { font-size: 0.78rem; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }

        /* ── Section ── */
        .section { padding: 70px 2rem; max-width: 1100px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 48px; }
        .section-tag {
            display: inline-block; background: #ede9fe; color: #5b21b6;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em;
            padding: 4px 12px; border-radius: 100px; margin-bottom: 12px;
        }
        .section-header h2 { font-size: 1.9rem; font-weight: 800; color: #0f172a; margin-bottom: 12px; }
        .section-header p { color: #64748b; font-size: 1rem; max-width: 500px; margin: 0 auto; line-height: 1.65; }

        /* ── Feature grid ── */
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .feature-card {
            background: white; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 28px; transition: box-shadow .2s, border-color .2s;
        }
        .feature-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); border-color: #c7d2fe; }
        .feature-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 16px;
        }
        .feature-card h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .feature-card p { font-size: 0.875rem; color: #64748b; line-height: 1.6; }

        /* ── Flow ── */
        .flow-section-wrap { background: #f1f5f9; }
        .flow-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            border-radius: 12px; overflow: hidden;
            border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .flow-step {
            background: white; border: 1px solid #e2e8f0;
            padding: 28px 24px; text-align: center;
            position: relative;
        }
        .flow-step:not(:last-child)::after {
            content: '→';
            position: absolute; right: -14px; top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem; color: #94a3b8; z-index: 1;
        }
        .flow-num {
            width: 36px; height: 36px; border-radius: 50%;
            background: #4f46e5; color: white;
            font-size: 0.85rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
        }
        .flow-step h4 { font-size: 0.9rem; font-weight: 600; color: #0f172a; margin-bottom: 6px; }
        .flow-step p { font-size: 0.8rem; color: #64748b; line-height: 1.5; }

        /* ── CTA bottom ── */
        .cta-section {
            background: linear-gradient(135deg, #312e81, #4f46e5);
            color: white; text-align: center; padding: 70px 2rem;
        }
        .cta-section h2 { font-size: 2rem; font-weight: 800; margin-bottom: 14px; }
        .cta-section p { color: rgba(255,255,255,0.75); font-size: 1rem; margin-bottom: 32px; }

        /* ── Footer ── */
        footer {
            background: #0f172a; color: #64748b;
            text-align: center; padding: 22px 2rem;
            font-size: 0.8rem;
        }

        /* colors */
        .c-indigo  { background: #ede9fe; color: #5b21b6; }
        .c-blue    { background: #dbeafe; color: #1d4ed8; }
        .c-violet  { background: #f3e8ff; color: #7c3aed; }
        .c-emerald { background: #d1fae5; color: #065f46; }
        .c-orange  { background: #ffedd5; color: #c2410c; }
        .c-teal    { background: #ccfbf1; color: #0f766e; }

        @media (max-width: 640px) {
            .stats-bar { gap: 28px; }
            .flow-step::after { display: none; }
        }
    </style>
</head>
<body>

    {{-- Nav --}}
    <nav>
        <a href="/" class="nav-brand">
            <i class="ri-node-tree"></i>
            Diagramas
        </a>
        @auth
            <a href="{{ route('admin.dashboard') }}" class="btn-primary">
                <i class="ri-dashboard-line"></i> Ir al Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="btn-primary">
                <i class="ri-login-circle-line"></i> Iniciar sesión
            </a>
        @endauth
    </nav>

    {{-- Hero --}}
    <section class="hero">
        <div class="hero-badge">
            <i class="ri-node-tree"></i>
            Portal de Infraestructura de Red
        </div>
        <h1>Visualiza y gestiona<br><span>toda tu red en un solo lugar</span></h1>
        <p>
            Diagramas centraliza el inventario, la topología y la visualización 3D de tu infraestructura de switches,
            con diagramas automáticos y vistas interactivas organizadas por cliente y área.
        </p>
        <div class="hero-cta">
            @auth
                <a href="{{ route('admin.dashboard') }}" class="btn-hero-primary">
                    <i class="ri-dashboard-line"></i> Ir al Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-hero-primary">
                    <i class="ri-login-circle-line"></i> Acceder al Portal
                </a>
            @endauth
            <a href="#funcionalidades" class="btn-hero-outline">
                <i class="ri-information-line"></i> Ver funcionalidades
            </a>
        </div>
    </section>

    {{-- Stats bar --}}
    <div class="stats-bar">
        <div class="stat">
            <div class="stat-icon"><i class="ri-server-line"></i></div>
            <div class="stat-label">Inventario de switches</div>
        </div>
        <div class="stat">
            <div class="stat-icon"><i class="ri-flow-chart"></i></div>
            <div class="stat-label">Topología interactiva</div>
        </div>
        <div class="stat">
            <div class="stat-icon"><i class="ri-box-3-line"></i></div>
            <div class="stat-label">Vista 3D isométrica</div>
        </div>
        <div class="stat">
            <div class="stat-icon"><i class="ri-image-line"></i></div>
            <div class="stat-label">Exportación PNG</div>
        </div>
    </div>

    {{-- Funcionalidades --}}
    <div id="funcionalidades">
        <div class="section">
            <div class="section-header">
                <span class="section-tag">Funcionalidades</span>
                <h2>Todo lo que necesitas para gestionar tu red</h2>
                <p>Desde el inventario detallado de cada switch hasta la visualización 3D de toda la infraestructura.</p>
            </div>

            <div class="feature-grid">

                <div class="feature-card">
                    <div class="feature-icon c-indigo"><i class="ri-building-line"></i></div>
                    <h3>Gestión por Clientes</h3>
                    <p>Organiza toda la infraestructura por cliente. Cada cliente tiene su propio inventario, áreas de red y topología independiente. Accede rápidamente desde el Hub de clientes.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon c-blue"><i class="ri-server-line"></i></div>
                    <h3>Inventario de Switches</h3>
                    <p>Vista detallada de cada switch: modelo, firmware, MAC, IP de gestión, número de serie, puertos activos, vecinos EDP y configuración completa descargable.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon c-violet"><i class="ri-flow-chart"></i></div>
                    <h3>Topología de Red Interactiva</h3>
                    <p>Visualización dinámica con vis-network de las conexiones entre switches. Agrupa por áreas, muestra conexiones entre sitios y filtra por cliente o lote de configuración.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon c-emerald"><i class="ri-git-branch-line"></i></div>
                    <h3>Diagrama de Puertos</h3>
                    <p>Por cada switch, visualiza un diagrama radial de sus puertos activos: identifica vecinos documentados en BD y dispositivos finales con iconos por tipo (AP, servidor, impresora, cámara y más).</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon c-orange"><i class="ri-image-line"></i></div>
                    <h3>Exportación PNG Automática</h3>
                    <p>Genera diagramas PNG de cada switch con sus puertos, vecinos e iconos de dispositivos. Opcionalmente incluye la tabla de VLANs. Generado automáticamente mediante Python y matplotlib.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon c-teal"><i class="ri-box-3-line"></i></div>
                    <h3>Vista 3D Isométrica e IVE</h3>
                    <p>Dos motores de visualización 3D: la vista Isométrica (Three.js, con rotación y zoom) y el IVE — Infrastructure Visualization Engine — para una perspectiva global de toda la red del cliente.</p>
                </div>

            </div>
        </div>
    </div>

    {{-- Flujo de uso --}}
    <div class="flow-section-wrap">
        <div class="section">
            <div class="section-header">
                <span class="section-tag">Flujo de uso</span>
                <h2>¿Cómo funciona el portal?</h2>
                <p>Desde la carga de archivos de configuración hasta la visualización completa de la red.</p>
            </div>

            <div class="flow-grid">
                <div class="flow-step">
                    <div class="flow-num">1</div>
                    <h4>Subir archivos</h4>
                    <p>Carga los archivos de configuración de los switches agrupados por cliente y área.</p>
                </div>
                <div class="flow-step">
                    <div class="flow-num">2</div>
                    <h4>Parseo automático</h4>
                    <p>El sistema extrae hostname, modelo, MAC, puertos activos, VLANs, vecinos EDP y rutas.</p>
                </div>
                <div class="flow-step">
                    <div class="flow-num">3</div>
                    <h4>Inventario y detalle</h4>
                    <p>Consulta el inventario por cliente, accede al detalle de cada switch y edita descripciones de puertos.</p>
                </div>
                <div class="flow-step">
                    <div class="flow-num">4</div>
                    <h4>Topología y 3D</h4>
                    <p>Visualiza la topología interactiva, el diagrama de puertos o la vista isométrica 3D de toda la infraestructura.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- CTA final --}}
    <section class="cta-section">
        <h2>Listo para empezar</h2>
        <p>Accede al portal con tus credenciales y comienza a visualizar tu infraestructura de red.</p>
        @auth
            <a href="{{ route('admin.dashboard') }}" class="btn-hero-primary">
                <i class="ri-dashboard-line"></i> Ir al Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="btn-hero-primary">
                <i class="ri-login-circle-line"></i> Acceder al Portal
            </a>
        @endauth
    </section>

    {{-- Footer --}}
    <footer>
        &copy; {{ date('Y') }} Diagramas &middot; Portal de Infraestructura de Red
    </footer>

</body>
</html>
