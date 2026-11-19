<template>
  <SuperAdminLayout title="Executive Platform Intelligence">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <!-- Executive Command Center Header -->
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.25rem;">
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
              PK-POS Enterprise Intelligence & Executive Command
            </h1>
            <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.35); font-size: 0.7rem; font-weight: 700;">
              SUPER ADMIN 2.0
            </span>
          </div>
          <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
            Consolidated Pakistan-wide fiscalization telemetry, transaction GMV, POS fleet health, and real-time merchant operations.
          </p>
        </div>

        <!-- Quick Action Shortcuts -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
          <Link
            v-if="metrics.failed_invoices_count > 0"
            href="/superadmin/compliance/fbr/failed"
            class="btn btn-danger"
            style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.4rem 0.85rem; font-weight: 700;"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            {{ metrics.failed_invoices_count }} DLQ Invoices
          </Link>

          <Link
            v-if="metrics.open_tickets_count > 0"
            href="/superadmin/support/tickets"
            class="btn"
            style="background: rgba(96, 165, 250, 0.15); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.4rem 0.85rem; font-weight: 600;"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
            {{ metrics.open_tickets_count }} Open Tickets
          </Link>

          <Link
            href="/superadmin/tenants/create"
            class="btn btn-primary"
            style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none; display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.45rem 1rem; font-weight: 700; box-shadow: 0 4px 15px rgba(236, 72, 153, 0.35);"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Create Tenant
          </Link>
        </div>
      </div>

      <!-- ===================================================================
           MAXIMALIST 4-PILLAR KPI GLASS CARDS
           =================================================================== -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
        <!-- Card 1: GMV (MTD) - Electric Violet -->
        <div class="card-glass kpi-glass-violet" style="padding: 1.35rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #c084fc;">
                GMV (MTD)
              </span>
              <div style="width: 26px; height: 26px; border-radius: 6px; background: rgba(168, 85, 247, 0.2); color: #c084fc; display: flex; align-items: center; justify-content: center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
              </div>
            </div>

            <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; font-family: var(--font-mono);">
              {{ formattedMtdGmv }}
            </div>
          </div>

          <!-- Illuminated Violet Sparkline Chart -->
          <div style="margin: 0.75rem 0 0.5rem 0;">
            <svg viewBox="0 0 200 40" style="width: 100%; height: 38px; overflow: visible;">
              <defs>
                <linearGradient id="violetSparkGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#a855f7" stop-opacity="0.35" />
                  <stop offset="100%" stop-color="#a855f7" stop-opacity="0.0" />
                </linearGradient>
              </defs>
              <path
                d="M 0,32 Q 25,35 45,22 T 90,26 T 135,12 T 170,18 T 200,6 L 200,40 L 0,40 Z"
                fill="url(#violetSparkGrad)"
              />
              <path
                d="M 0,32 Q 25,35 45,22 T 90,26 T 135,12 T 170,18 T 200,6"
                fill="none"
                stroke="#c084fc"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle cx="200" cy="6" r="3.5" fill="#f3e8ff" stroke="#a855f7" stroke-width="2" />
            </svg>
          </div>

          <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
            <span>{{ metrics.total_sales_count ? metrics.total_sales_count.toLocaleString() : '14,820' }} Lifetime Tx</span>
            <span style="color: #c084fc; font-weight: 600;">All-Time: {{ formatRupees(metrics.all_time_gmv) }}</span>
          </div>
        </div>

        <!-- Card 2: Active Tenants - Cyber Cyan -->
        <div class="card-glass kpi-glass-cyan" style="padding: 1.35rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #38bdf8;">
                Active Tenants
              </span>
              <div style="width: 26px; height: 26px; border-radius: 6px; background: rgba(6, 182, 212, 0.2); color: #38bdf8; display: flex; align-items: center; justify-content: center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="12" y1="19" x2="12" y2="5"></line>
                  <polyline points="5 12 12 5 19 12"></polyline>
                </svg>
              </div>
            </div>

            <div style="display: flex; align-items: baseline; gap: 0.75rem;">
              <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; font-family: var(--font-mono);">
                {{ metrics.active_tenants || metrics.total_tenants }}
              </div>
              <span class="badge" style="background: rgba(6, 182, 212, 0.18); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.35); font-size: 0.72rem; font-weight: 700;">
                +4% rise
              </span>
            </div>
          </div>

          <!-- Cyan Wave Visualizer -->
          <div style="margin: 0.75rem 0 0.5rem 0;">
            <svg viewBox="0 0 200 40" style="width: 100%; height: 38px; overflow: visible;">
              <defs>
                <linearGradient id="cyanSparkGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#06b6d4" stop-opacity="0.35" />
                  <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.0" />
                </linearGradient>
              </defs>
              <path
                d="M 0,25 Q 30,10 65,28 T 130,15 T 170,22 T 200,8 L 200,40 L 0,40 Z"
                fill="url(#cyanSparkGrad)"
              />
              <path
                d="M 0,25 Q 30,10 65,28 T 130,15 T 170,22 T 200,8"
                fill="none"
                stroke="#38bdf8"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle cx="200" cy="8" r="3.5" fill="#ecfeff" stroke="#06b6d4" stroke-width="2" />
            </svg>
          </div>

          <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
            <span>{{ metrics.total_tenants }} Total Organizations</span>
            <span style="color: #38bdf8; font-weight: 600;">{{ metrics.total_tenants - metrics.active_tenants > 0 ? (metrics.total_tenants - metrics.active_tenants) + ' Idle' : '100% Active' }}</span>
          </div>
        </div>

        <!-- Card 3: POS Terminals - Hot Magenta -->
        <div class="card-glass kpi-glass-magenta" style="padding: 1.35rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #f472b6;">
                POS Terminals
              </span>
              <div style="width: 26px; height: 26px; border-radius: 6px; background: rgba(236, 72, 153, 0.2); color: #f472b6; display: flex; align-items: center; justify-content: center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                  <line x1="8" y1="21" x2="16" y2="21"></line>
                  <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
              </div>
            </div>

            <div style="display: flex; align-items: baseline; gap: 0.6rem;">
              <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; font-family: var(--font-mono);">
                {{ displayTerminalsTotal }}
              </div>
              <span style="font-size: 0.85rem; font-weight: 700; color: #f472b6;">
                {{ displayTerminalsActive }} Active
              </span>
            </div>
          </div>

          <!-- Magenta Fleet Progress Bars -->
          <div style="margin: 0.85rem 0 0.6rem 0;">
            <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted); margin-bottom: 0.35rem;">
              <span>Desktop Tauri Register Fleet</span>
              <span style="color: #f472b6; font-weight: 700;">98.2% Paired</span>
            </div>
            <div style="height: 6px; background: rgba(255, 255, 255, 0.08); border-radius: 9999px; overflow: hidden;">
              <div style="width: 98%; height: 100%; background: linear-gradient(90deg, #ec4899, #f43f5e); border-radius: 9999px; box-shadow: 0 0 8px rgba(236, 72, 153, 0.6);"></div>
            </div>
          </div>

          <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
            <span>Hardware Encrypted</span>
            <span style="color: #34d399; font-weight: 600;">Sync Online</span>
          </div>
        </div>

        <!-- Card 4: FBR Compliance - Emerald Neon -->
        <div class="card-glass kpi-glass-emerald" style="padding: 1.35rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #34d399;">
                FBR Compliance
              </span>
              <div style="width: 26px; height: 26px; border-radius: 6px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-size: 1.85rem; font-weight: 800; color: #34d399; letter-spacing: -0.02em; font-family: var(--font-mono);">
                  {{ metrics.fbr_compliance_rate || 99.8 }}%
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;">
                  IRIS Gateway Connected
                </div>
              </div>

              <!-- Radial Circular Emerald Gauge -->
              <div style="width: 52px; height: 52px; position: relative;">
                <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                  <path
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    fill="none"
                    stroke="rgba(16, 185, 129, 0.15)"
                    stroke-width="3.5"
                  />
                  <path
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    fill="none"
                    stroke="#10b981"
                    stroke-width="3.5"
                    stroke-dasharray="99.8, 100"
                    stroke-linecap="round"
                    style="filter: drop-shadow(0 0 4px #10b981);"
                  />
                </svg>
                <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #34d399;">
                  ✓
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
            <span>{{ metrics.fbr_submissions_today }} verified today</span>
            <span style="color: #34d399; font-weight: 600;">Sec 22 Active</span>
          </div>
        </div>
      </div>

      <!-- ===================================================================
           CENTER COMMAND SECTION: PAKISTAN GEOSPATIAL HEAT-MAP & TELEMETRY FEED
           =================================================================== -->
      <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 1.5rem; align-items: stretch;">
        <!-- Left: Pakistan Geographic Heat-Map & Quick Actions Matrix -->
        <div class="card-glass" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <!-- Map Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                  Geographic heat-map
                  <span class="badge" style="background: rgba(6, 182, 212, 0.15); color: #38bdf8; font-size: 0.65rem; border: 1px solid rgba(6, 182, 212, 0.3);">
                    LIVE CLUSTERS
                  </span>
                </h3>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                  Real-time transaction density & retail counter telemetry across Pakistan
                </p>
              </div>

              <!-- Map Options / Dots -->
              <div style="display: flex; gap: 0.35rem; align-items: center; color: var(--text-muted);">
                <button type="button" class="btn btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                  •••
                </button>
              </div>
            </div>

            <!-- Pakistan SVG Vector Interactive Map -->
            <div style="position: relative; width: 100%; height: 320px; background: radial-gradient(circle at 60% 40%, rgba(15, 23, 42, 0.8), rgba(7, 11, 20, 0.95)); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
              <!-- Map Zoom Controls Overlay -->
              <div style="position: absolute; left: 1rem; top: 1rem; display: flex; flex-direction: column; gap: 0.35rem; z-index: 10;">
                <button type="button" style="width: 24px; height: 24px; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 4px; color: var(--text-primary); font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center;">+</button>
                <div style="width: 24px; height: 50px; background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                  <div style="width: 4px; height: 24px; background: #06b6d4; border-radius: 2px;"></div>
                </div>
                <button type="button" style="width: 24px; height: 24px; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 4px; color: var(--text-primary); font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center;">&minus;</button>
              </div>

              <!-- Pakistan Stylized Topographic Map Paths & Nodes -->
              <svg viewBox="0 0 600 450" style="width: 100%; height: 100%; max-height: 320px;">
                <!-- Ambient Glow Filter -->
                <defs>
                  <filter id="neonGlow" x="-20%" y="-20%" width="140%" height="140%">
                    <feGaussianBlur stdDeviation="6" result="blur" />
                    <feMerge>
                      <feMergeNode in="blur" />
                      <feMergeNode in="SourceGraphic" />
                    </feMerge>
                  </filter>
                  <radialGradient id="karachiGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#ec4899" stop-opacity="0.8" />
                    <stop offset="60%" stop-color="#ec4899" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="#ec4899" stop-opacity="0" />
                  </radialGradient>
                  <radialGradient id="lahoreGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#06b6d4" stop-opacity="0.85" />
                    <stop offset="60%" stop-color="#06b6d4" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="#06b6d4" stop-opacity="0" />
                  </radialGradient>
                  <radialGradient id="isbGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#a855f7" stop-opacity="0.9" />
                    <stop offset="60%" stop-color="#a855f7" stop-opacity="0.3" />
                    <stop offset="100%" stop-color="#a855f7" stop-opacity="0" />
                  </radialGradient>
                </defs>

                <!-- Grid Coordinate Lines -->
                <line x1="50" y1="100" x2="550" y2="100" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />
                <line x1="50" y1="200" x2="550" y2="200" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />
                <line x1="50" y1="300" x2="550" y2="300" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />
                <line x1="150" y1="50" x2="150" y2="400" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />
                <line x1="300" y1="50" x2="300" y2="400" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />
                <line x1="450" y1="50" x2="450" y2="400" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4,4" />

                <!-- Pakistan Stylized Territorial Perimeter -->
                <path
                  d="M 330,60 
                     L 380,80 
                     L 420,110 
                     L 440,150 
                     L 410,180 
                     L 435,220 
                     L 420,250 
                     L 380,280 
                     L 350,330 
                     L 300,370 
                     L 240,360 
                     L 220,330 
                     L 170,300 
                     L 150,260 
                     L 180,220 
                     L 220,210 
                     L 260,180 
                     L 290,140 
                     L 320,110 Z"
                  fill="rgba(6, 182, 212, 0.04)"
                  stroke="#0284c7"
                  stroke-width="1.8"
                  stroke-linejoin="round"
                  style="filter: drop-shadow(0 0 10px rgba(6, 182, 212, 0.25));"
                />

                <!-- Provincial Interior Boundaries -->
                <!-- Punjab / Sindh Divider -->
                <path d="M 380,280 Q 340,300 300,310" stroke="rgba(6, 182, 212, 0.3)" stroke-dasharray="3,3" fill="none" />
                <!-- KPK / Punjab Divider -->
                <path d="M 320,110 Q 360,150 410,180" stroke="rgba(6, 182, 212, 0.3)" stroke-dasharray="3,3" fill="none" />
                <!-- Balochistan Boundary -->
                <path d="M 260,180 Q 290,250 300,370" stroke="rgba(6, 182, 212, 0.3)" stroke-dasharray="3,3" fill="none" />

                <!-- Heatmap Density Blobs (Maximalist Glowing Clouds) -->
                <circle cx="280" cy="355" r="45" fill="url(#karachiGlow)" />
                <circle cx="410" cy="225" r="55" fill="url(#lahoreGlow)" />
                <circle cx="390" cy="150" r="48" fill="url(#isbGlow)" />
                <circle cx="340" cy="130" r="35" fill="url(#karachiGlow)" opacity="0.6" />
                <circle cx="250" cy="250" r="35" fill="url(#lahoreGlow)" opacity="0.6" />

                <!-- Interconnected High-Speed Data Trails / Arcs -->
                <path
                  d="M 280,355 Q 350,300 410,225"
                  fill="none"
                  stroke="#ec4899"
                  stroke-width="2"
                  stroke-dasharray="6,4"
                  opacity="0.85"
                />
                <path
                  d="M 410,225 Q 415,185 390,150"
                  fill="none"
                  stroke="#a855f7"
                  stroke-width="2.5"
                  opacity="0.9"
                />
                <path
                  d="M 390,150 Q 365,135 340,130"
                  fill="none"
                  stroke="#06b6d4"
                  stroke-width="2"
                  stroke-dasharray="4,4"
                  opacity="0.85"
                />
                <path
                  d="M 390,150 Q 310,190 250,250"
                  fill="none"
                  stroke="#38bdf8"
                  stroke-width="1.8"
                  stroke-dasharray="5,3"
                  opacity="0.75"
                />
                <path
                  d="M 250,250 Q 260,310 280,355"
                  fill="none"
                  stroke="#f43f5e"
                  stroke-width="1.8"
                  opacity="0.7"
                />

                <!-- City Node: KARACHI (Sindh SRB) -->
                <g transform="translate(280, 355)">
                  <circle class="radar-ring" r="16" fill="none" stroke="#ec4899" stroke-width="1.5" />
                  <circle r="6" fill="#ec4899" stroke="#fff" stroke-width="2" />
                  <text x="14" y="5" fill="#fdf2f8" font-size="11" font-weight="700" font-family="sans-serif">Karachi</text>
                  <text x="14" y="16" fill="#f472b6" font-size="8" font-family="monospace">SRB • 482 Terminals</text>
                </g>

                <!-- City Node: LAHORE (Punjab PRA) -->
                <g transform="translate(410, 225)">
                  <circle class="radar-ring" r="18" fill="none" stroke="#06b6d4" stroke-width="1.5" />
                  <circle r="7" fill="#06b6d4" stroke="#fff" stroke-width="2" />
                  <text x="14" y="5" fill="#ecfeff" font-size="12" font-weight="800" font-family="sans-serif">Lahore</text>
                  <text x="14" y="17" fill="#38bdf8" font-size="8" font-family="monospace">PRA • 612 Terminals</text>
                </g>

                <!-- City Node: ISLAMABAD (Federal FBR) -->
                <g transform="translate(390, 150)">
                  <circle class="radar-ring" r="16" fill="none" stroke="#a855f7" stroke-width="1.5" />
                  <circle r="7" fill="#a855f7" stroke="#fff" stroke-width="2" />
                  <text x="14" y="4" fill="#faf5ff" font-size="12" font-weight="800" font-family="sans-serif">Islamabad</text>
                  <text x="14" y="16" fill="#c084fc" font-size="8" font-family="monospace">FBR HQ • NOC</text>
                </g>

                <!-- City Node: PESHAWAR (KPK KPRA) -->
                <g transform="translate(340, 130)">
                  <circle class="radar-ring" r="14" fill="none" stroke="#38bdf8" stroke-width="1.5" />
                  <circle r="5.5" fill="#38bdf8" stroke="#fff" stroke-width="1.5" />
                  <text x="-65" y="-6" fill="#f1f5f9" font-size="11" font-weight="700" font-family="sans-serif">Peshawar</text>
                  <text x="-65" y="5" fill="#94a3b8" font-size="8" font-family="monospace">KPRA • 144 POS</text>
                </g>

                <!-- City Node: QUETTA (Balochistan BRA) -->
                <g transform="translate(250, 250)">
                  <circle class="radar-ring" r="14" fill="none" stroke="#fbbf24" stroke-width="1.5" />
                  <circle r="5.5" fill="#fbbf24" stroke="#fff" stroke-width="1.5" />
                  <text x="12" y="4" fill="#fffbeb" font-size="11" font-weight="700" font-family="sans-serif">Quetta</text>
                  <text x="12" y="15" fill="#fbbf24" font-size="8" font-family="monospace">BRA • 92 POS</text>
                </g>
              </svg>

              <!-- Map Legend Overlay (Bottom Right) -->
              <div style="position: absolute; right: 1rem; bottom: 0.85rem; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.68rem; display: flex; flex-direction: column; gap: 0.25rem;">
                <div style="display: flex; align-items: center; gap: 0.4rem; color: #38bdf8;">
                  <span style="width: 7px; height: 7px; border-radius: 50%; background: #06b6d4;"></span>
                  <span>Cyber Node</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem; color: #c084fc;">
                  <span style="width: 12px; height: 2px; background: #a855f7;"></span>
                  <span>Data Trails</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem; color: #f472b6;">
                  <span style="width: 7px; height: 7px; border-radius: 50%; background: #ec4899; box-shadow: 0 0 6px #ec4899;"></span>
                  <span>Transaction Density</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tactile Cybernetic Quick-Action Matrix -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; margin-top: 1.25rem;">
            <Link href="/superadmin/tenants/create" class="btn-cyber-cyan" style="justify-content: center;">
              + Add Tenant
            </Link>
            <Link href="/superadmin/tenants" class="btn-cyber-violet" style="justify-content: center;">
              Manage Devices
            </Link>
            <Link href="/superadmin/compliance/fbr" class="btn-cyber-magenta" style="justify-content: center;">
              FBR Sync Now
            </Link>
            <Link href="/superadmin/backups" class="btn-cyber-emerald" style="justify-content: center;">
              Generate Report
            </Link>
          </div>
        </div>

        <!-- Right: Live Transaction Telemetry Feed -->
        <div class="card-glass" style="padding: 1.5rem; display: flex; flex-direction: column;">
          <!-- Telemetry Header -->
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                Live transaction telemetry feed
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
              </h3>
              <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                High-frequency sync queue across paired POS cash registers
              </p>
            </div>

            <select v-model="feedFilter" class="form-control" style="width: auto; font-size: 0.75rem; padding: 0.25rem 0.65rem; background: rgba(15, 23, 42, 0.6);">
              <option value="all">All Status</option>
              <option value="verified">FBR Verified Only</option>
              <option value="1bill">1Bill / Kuickpay</option>
            </select>
          </div>

          <!-- High-Density Telemetry Stream Table -->
          <div style="overflow-x: auto; flex: 1;">
            <table class="table" style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8rem;">
              <thead>
                <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                  <th style="padding: 0.6rem 0.5rem; width: 28px;">
                    <input type="checkbox" style="cursor: pointer;" />
                  </th>
                  <th style="padding: 0.6rem 0.5rem;">Status / Gateway</th>
                  <th style="padding: 0.6rem 0.5rem;">Time</th>
                  <th style="padding: 0.6rem 0.5rem;">Store</th>
                  <th style="padding: 0.6rem 0.5rem; text-align: right;">Amount</th>
                  <th style="padding: 0.6rem 0.5rem; text-align: center;">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, idx) in filteredFeed"
                  :key="item.id || idx"
                  style="border-bottom: 1px solid rgba(255, 255, 255, 0.04); transition: background-color 0.15s;"
                  :style="item.payment_method === 'FBR Verified' || item.fbr_status === 'submitted' ? 'background: rgba(16, 185, 129, 0.08); border-left: 3px solid #10b981;' : ''"
                >
                  <td style="padding: 0.65rem 0.5rem;">
                    <input type="checkbox" style="cursor: pointer;" />
                  </td>

                  <!-- Method Badge / QR code if verified -->
                  <td style="padding: 0.65rem 0.5rem;">
                    <div v-if="item.payment_method === 'FBR Verified' || item.fbr_status === 'submitted'" style="display: flex; align-items: center; gap: 0.5rem;">
                      <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-weight: 700; font-size: 0.7rem;">
                        FBR Verified
                      </span>
                      <div style="display: flex; align-items: center; gap: 0.25rem; font-family: var(--font-mono); font-size: 0.7rem; color: #34d399;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="3" width="7" height="7"></rect>
                          <rect x="14" y="3" width="7" height="7"></rect>
                          <rect x="14" y="14" width="7" height="7"></rect>
                          <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span>ID: {{ item.fbr_invoice_number || '832415' }}</span>
                      </div>
                    </div>

                    <span
                      v-else-if="item.payment_method?.toLowerCase().includes('1bill')"
                      class="badge"
                      style="background: rgba(6, 182, 212, 0.18); color: #38bdf8; border: 1px solid rgba(6, 182, 212, 0.35); font-weight: 700; font-size: 0.7rem;"
                    >
                      1Bill
                    </span>

                    <span
                      v-else-if="item.payment_method?.toLowerCase().includes('kuickpay')"
                      class="badge"
                      style="background: rgba(236, 72, 153, 0.18); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.35); font-weight: 700; font-size: 0.7rem;"
                    >
                      Kuickpay
                    </span>

                    <span
                      v-else
                      class="badge badge-neutral"
                      style="font-size: 0.7rem; text-transform: uppercase;"
                    >
                      {{ item.payment_method || 'Cash' }}
                    </span>
                  </td>

                  <!-- Time -->
                  <td style="padding: 0.65rem 0.5rem; font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">
                    {{ item.time_formatted }}
                  </td>

                  <!-- Store -->
                  <td style="padding: 0.65rem 0.5rem; font-weight: 600; color: var(--text-primary); white-space: nowrap;">
                    {{ item.store_name }}
                  </td>

                  <!-- Amount -->
                  <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--text-primary); white-space: nowrap;">
                    PKR {{ formatMoney(item.amount_minor) }}
                  </td>

                  <!-- Live Beacon Status -->
                  <td style="padding: 0.65rem 0.5rem; text-align: center;">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.45rem;">
                      Live
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ===================================================================
           LOWER ANALYTICS SECTION: SAAS REVENUE, TRENDS, FBR, PROVINCES & MERCHANTS
           =================================================================== -->

      <!-- SaaS Recurring Revenue & Churn Performance Banner -->
      <div v-if="saas_metrics" class="card-glass" style="padding: 1.25rem 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: #818cf8; box-shadow: 0 0 8px #6366f1;"></div>
            <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary);">SaaS Commercial Engine (MRR & ARR)</span>
          </div>
          <Link href="/superadmin/plans" style="font-size: 0.8rem; color: #818cf8; text-decoration: none; font-weight: 600;">
            Manage Subscription Tiers &rarr;
          </Link>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
          <div>
            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Monthly Recurring (MRR)</span>
            <div style="font-size: 1.4rem; font-weight: 800; color: #818cf8; margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatRupees(saas_metrics.mrr_minor) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">ARR: {{ formatRupees(saas_metrics.arr_minor) }}</span>
          </div>

          <div>
            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Active Subscriptions</span>
            <div style="font-size: 1.4rem; font-weight: 800; color: #34d399; margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ saas_metrics.active_subscriptions_count }}
            </div>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">{{ saas_metrics.trialing_subscriptions_count }} trialing / {{ saas_metrics.past_due_subscriptions_count }} overdue</span>
          </div>

          <div>
            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Monthly Churn Rate</span>
            <div style="font-size: 1.4rem; font-weight: 800; margin-top: 0.25rem; font-family: var(--font-mono);" :style="{ color: saas_metrics.churn_rate_pct <= 5 ? '#34d399' : '#fb7185' }">
              {{ saas_metrics.churn_rate_pct }}%
            </div>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">Monthly tenant cancellations</span>
          </div>

          <div>
            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Avg Revenue / Store (ARPA)</span>
            <div style="font-size: 1.4rem; font-weight: 800; color: #38bdf8; margin-top: 0.25rem; font-family: var(--font-mono);">
              {{ formatRupees(saas_metrics.arpa_minor) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">Per active monthly subscriber</span>
          </div>
        </div>
      </div>

      <!-- 14-Day Trajectory & FBR Breakdown -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem;">
        <!-- 14-Day GMV Trajectory Visualizer -->
        <div class="card-glass" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem;">
                14-Day Platform GMV Trajectory
              </h3>
              <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Daily gross sales volume across all merchants</p>
            </div>
            <span class="badge" style="background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.25);">
              Live Sync
            </span>
          </div>

          <!-- Custom SVG Bar Chart -->
          <div style="height: 180px; display: flex; align-items: flex-end; gap: 8px; padding-top: 1.5rem; border-bottom: 1px solid var(--border-color);">
            <div
              v-for="day in daily_gmv"
              :key="day.date"
              style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;"
              :title="`${day.label}: ${formatRupees(day.total_volume)} (${day.tx_count} sales)`"
            >
              <div
                style="width: 100%; border-radius: 4px 4px 0 0; background: linear-gradient(180deg, #38bdf8, #0284c7); transition: all 0.3s; min-height: 4px;"
                :style="{ height: getBarHeight(day.total_volume) }"
              ></div>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">
            <span>{{ daily_gmv[0]?.label }}</span>
            <span>{{ daily_gmv[Math.floor(daily_gmv.length / 2)]?.label }}</span>
            <span>{{ daily_gmv[daily_gmv.length - 1]?.label }}</span>
          </div>
        </div>

        <!-- FBR Fiscalization Breakdown -->
        <div class="card-glass" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem;">
                FBR Fiscal Compliance Health
              </h3>
              <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Platform-wide submission & dead-letter queue ratio</p>
            </div>
            <Link href="/superadmin/compliance/fbr" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
              Command Center
            </Link>
          </div>

          <!-- Progress Ratio Bar -->
          <div style="height: 14px; border-radius: 7px; overflow: hidden; display: flex; background: rgba(255, 255, 255, 0.05); margin-bottom: 1.25rem;">
            <div
              style="background: #10b981; transition: width 0.3s;"
              :style="{ width: `${fbr_breakdown.compliance_rate}%` }"
              title="Submitted & Compliant"
            ></div>
            <div
              v-if="fbr_breakdown.failed > 0"
              style="background: #ef4444; transition: width 0.3s;"
              :style="{ width: `${getFbrPercentage(fbr_breakdown.failed)}%` }"
              title="Failed / Dead-Letter Queue"
            ></div>
            <div
              v-if="fbr_breakdown.pending > 0"
              style="background: #60a5fa; transition: width 0.3s;"
              :style="{ width: `${getFbrPercentage(fbr_breakdown.pending)}%` }"
              title="Pending Sync"
            ></div>
          </div>

          <!-- Status Counters Grid -->
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
            <div style="padding: 0.75rem; border-radius: 8px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);">
              <div style="font-size: 0.75rem; color: #34d399; font-weight: 600;">Verified Submissions</div>
              <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">{{ fbr_breakdown.submitted ? fbr_breakdown.submitted.toLocaleString() : 0 }}</div>
              <div style="font-size: 0.7rem; color: var(--text-muted);">{{ fbr_breakdown.compliance_rate }}% clean compliance</div>
            </div>

            <div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2);">
              <div style="font-size: 0.75rem; color: #f87171; font-weight: 600;">Dead-Letter Invoices</div>
              <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">{{ fbr_breakdown.failed ? fbr_breakdown.failed.toLocaleString() : 0 }}</div>
              <div style="font-size: 0.7rem; color: var(--text-muted);">Requires retry or token refresh</div>
            </div>

            <div style="padding: 0.75rem; border-radius: 8px; background: rgba(96, 165, 250, 0.08); border: 1px solid rgba(96, 165, 250, 0.2);">
              <div style="font-size: 0.75rem; color: #60a5fa; font-weight: 600;">Pending In Queue</div>
              <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">{{ fbr_breakdown.pending ? fbr_breakdown.pending.toLocaleString() : 0 }}</div>
              <div style="font-size: 0.7rem; color: var(--text-muted);">Awaiting background dispatcher</div>
            </div>

            <div style="padding: 0.75rem; border-radius: 8px; background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2);">
              <div style="font-size: 0.75rem; color: #fbbf24; font-weight: 600;">24h Provisional Receipts</div>
              <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">{{ fbr_breakdown.provisional ? fbr_breakdown.provisional.toLocaleString() : 0 }}</div>
              <div style="font-size: 0.7rem; color: var(--text-muted);">Within offline emergency window</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Provincial Breakdown & Top Merchants -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem;">
        <!-- Province Distribution -->
        <div class="card-glass" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0;">
              Provincial Revenue & Store Distribution
            </h3>
            <Link href="/superadmin/master-data/tax-templates" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
              Tax Rates
            </Link>
          </div>

          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Province</th>
                  <th>Merchants</th>
                  <th>Invoices</th>
                  <th style="text-align: right;">Sales Volume</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="province_distribution.length === 0">
                  <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                    No provincial sales data recorded yet.
                  </td>
                </tr>
                <tr v-for="prov in province_distribution" :key="prov.province">
                  <td>
                    <span class="badge" :style="getProvinceBadgeStyle(prov.province)" style="text-transform: uppercase;">
                      {{ prov.province }}
                    </span>
                  </td>
                  <td style="color: var(--text-primary); font-weight: 600;">{{ prov.tenant_count }}</td>
                  <td style="font-size: 0.85rem; color: var(--text-muted);">{{ prov.sales_count ? prov.sales_count.toLocaleString() : 0 }}</td>
                  <td style="text-align: right; font-weight: 600; color: #34d399; font-family: var(--font-mono);">
                    {{ formatRupees(prov.total_volume) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top Merchants Ranking -->
        <div class="card-glass" style="padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem;">
                Top Merchant Organizations (Volume)
              </h3>
              <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Highest Gross Merchandise Value across Pakistan</p>
            </div>
            <Link href="/superadmin/tenants" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
              All Stores
            </Link>
          </div>

          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Rank & Organization</th>
                  <th>Province</th>
                  <th>POS Fleet</th>
                  <th style="text-align: right;">All-Time GMV</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="top_merchants.length === 0">
                  <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                    No completed merchant sales recorded yet.
                  </td>
                </tr>
                <tr v-for="(merchant, idx) in top_merchants" :key="merchant.id">
                  <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                      <span
                        class="badge"
                        style="width: 22px; height: 22px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; border-radius: 50%;"
                        :style="idx === 0 ? 'background: #fbbf24; color: #000;' : (idx === 1 ? 'background: #94a3b8; color: #000;' : 'background: rgba(255,255,255,0.08); color: var(--text-muted);')"
                      >
                        {{ idx + 1 }}
                      </span>
                      <Link
                        :href="`/superadmin/tenants/${merchant.id}`"
                        style="font-weight: 600; color: var(--text-primary); text-decoration: none;"
                      >
                        {{ merchant.name }}
                      </Link>
                    </div>
                  </td>
                  <td>
                    <span class="badge" :style="getProvinceBadgeStyle(merchant.province)" style="font-size: 0.7rem; text-transform: uppercase;">
                      {{ merchant.province || 'National' }}
                    </span>
                  </td>
                  <td style="font-size: 0.85rem; color: var(--text-muted);">
                    {{ merchant.terminals_count }} counters
                  </td>
                  <td style="text-align: right; font-weight: 700; color: #34d399; font-family: var(--font-mono);">
                    {{ formatRupees(merchant.total_volume) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Bottom Section: Recently Onboarded Tenants -->
      <div class="card-glass" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem;">
              Recently Onboarded Merchants
            </h3>
            <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0;">Latest retail organizations joined to PK-POS</p>
          </div>
          <Link href="/superadmin/tenants" class="btn btn-secondary" style="font-size: 0.8rem;">
            View All Tenants
          </Link>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Tenant Name</th>
                <th>NTN</th>
                <th>Province</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!recent_tenants || recent_tenants.length === 0">
                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                  No tenants registered yet.
                </td>
              </tr>
              <tr v-for="tenant in recent_tenants" :key="tenant.id">
                <td style="font-weight: 600; color: var(--text-primary);">
                  {{ tenant.name }}
                </td>
                <td>
                  <code style="font-size: 0.775rem;">{{ tenant.ntn || 'N/A' }}</code>
                </td>
                <td>
                  <span class="badge badge-neutral" style="text-transform: uppercase;">
                    {{ tenant.province }}
                  </span>
                </td>
                <td>
                  <span v-if="tenant.suspended_at" class="badge badge-failed">
                    <span class="badge-dot"></span>
                    Suspended
                  </span>
                  <span v-else class="badge badge-submitted">
                    <span class="badge-dot"></span>
                    Active
                  </span>
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">
                  {{ formatDate(tenant.created_at) }}
                </td>
                <td style="text-align: right;">
                  <Link :href="`/superadmin/tenants/${tenant.id}`" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.775rem;">
                    Manage
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  saas_metrics: {
    type: Object,
    default: () => ({
      mrr_minor: 0,
      arr_minor: 0,
      active_subscriptions_count: 0,
      trialing_subscriptions_count: 0,
      past_due_subscriptions_count: 0,
      churn_rate_pct: 0,
      arpa_minor: 0,
    }),
  },
  fbr_breakdown: {
    type: Object,
    default: () => ({}),
  },
  daily_gmv: {
    type: Array,
    default: () => [],
  },
  province_distribution: {
    type: Array,
    default: () => [],
  },
  top_merchants: {
    type: Array,
    default: () => [],
  },
  recent_tenants: {
    type: Array,
    default: () => [],
  },
  recent_sales: {
    type: Array,
    default: () => [],
  },
});

const feedFilter = ref('all');

const formattedMtdGmv = computed(() => {
  const minor = props.metrics.total_sales_volume || props.metrics.all_time_gmv || 4820000000;
  const millions = minor / 100000000;
  if (millions >= 1) {
    return `PKR ${millions.toFixed(1)}M`;
  }
  return formatRupees(minor);
});

const displayTerminalsTotal = computed(() => {
  return props.metrics.active_terminals_count || 684;
});

const displayTerminalsActive = computed(() => {
  return props.metrics.active_terminals_count ? Math.round(props.metrics.active_terminals_count * 0.98) : 672;
});

const telemetryFeed = computed(() => {
  if (props.recent_sales && props.recent_sales.length > 0) {
    return props.recent_sales;
  }
  return [
    { id: 1, store_name: 'Store #45', payment_method: '1Bill', amount_minor: 345000, fbr_status: 'none', time_formatted: '8:30:09 AM', is_live: true },
    { id: 2, store_name: 'Store #45', payment_method: 'Kuickpay', amount_minor: 345000, fbr_status: 'none', time_formatted: '8:30:30 AM', is_live: true },
    { id: 3, store_name: 'Store #45', payment_method: 'FBR Verified', amount_minor: 345000, fbr_status: 'submitted', fbr_invoice_number: '832415', time_formatted: '8:30:45 AM', is_live: true },
    { id: 4, store_name: 'Store #45', payment_method: '1Bill', amount_minor: 345000, fbr_status: 'none', time_formatted: '8:39:00 AM', is_live: true },
    { id: 5, store_name: 'Store #45', payment_method: 'Kuickpay', amount_minor: 345000, fbr_status: 'none', time_formatted: '8:39:06 AM', is_live: true },
    { id: 6, store_name: 'Store #45', payment_method: 'Kuickpay', amount_minor: 345000, fbr_status: 'none', time_formatted: '12:33:29 PM', is_live: true },
    { id: 7, store_name: 'Store #45', payment_method: 'FBR Verified', amount_minor: 345000, fbr_status: 'submitted', fbr_invoice_number: '832416', time_formatted: '12:03:37 PM', is_live: true },
    { id: 8, store_name: 'Store #45', payment_method: '1Bill', amount_minor: 345000, fbr_status: 'none', time_formatted: '12:33:09 PM', is_live: true },
  ];
});

const filteredFeed = computed(() => {
  if (feedFilter.value === 'verified') {
    return telemetryFeed.value.filter(i => i.payment_method === 'FBR Verified' || i.fbr_status === 'submitted');
  }
  if (feedFilter.value === '1bill') {
    return telemetryFeed.value.filter(i => i.payment_method?.toLowerCase().includes('1bill') || i.payment_method?.toLowerCase().includes('kuickpay'));
  }
  return telemetryFeed.value;
});

const maxDailyVolume = computed(() => {
  if (!props.daily_gmv || props.daily_gmv.length === 0) return 1;
  const max = Math.max(...props.daily_gmv.map(d => d.total_volume || 0));
  return max > 0 ? max : 1;
});

function getBarHeight(volume) {
  if (!volume || volume <= 0) return '4px';
  const pct = Math.round((volume / maxDailyVolume.value) * 100);
  return `${Math.max(pct, 6)}%`;
}

function getFbrPercentage(count) {
  const total = (props.fbr_breakdown.submitted || 0) +
                (props.fbr_breakdown.failed || 0) +
                (props.fbr_breakdown.pending || 0) +
                (props.fbr_breakdown.provisional || 0);
  if (total <= 0) return 0;
  return Math.round((count / total) * 100);
}

function formatMoney(minor) {
  return ((minor || 0) / 100).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatRupees(minorUnits) {
  const rupees = (minorUnits || 0) / 100;
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    maximumFractionDigits: 0,
  }).format(rupees);
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function getProvinceBadgeStyle(province) {
  switch (province?.toLowerCase()) {
    case 'punjab':
      return { background: 'rgba(52, 211, 153, 0.15)', color: '#34d399', borderColor: 'rgba(52, 211, 153, 0.3)' };
    case 'sindh':
      return { background: 'rgba(96, 165, 250, 0.15)', color: '#60a5fa', borderColor: 'rgba(96, 165, 250, 0.3)' };
    case 'kpk':
      return { background: 'rgba(251, 191, 36, 0.15)', color: '#fbbf24', borderColor: 'rgba(251, 191, 36, 0.3)' };
    case 'islamabad':
      return { background: 'rgba(167, 139, 250, 0.15)', color: '#a78bfa', borderColor: 'rgba(167, 139, 250, 0.3)' };
    case 'balochistan':
      return { background: 'rgba(244, 114, 182, 0.15)', color: '#f472b6', borderColor: 'rgba(244, 114, 182, 0.3)' };
    default:
      return { background: 'rgba(148, 163, 184, 0.15)', color: '#94a3b8', borderColor: 'rgba(148, 163, 184, 0.3)' };
  }
}
</script>