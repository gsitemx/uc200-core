<section class="hero-panel">
    <div>
        <span class="eyebrow">Setup requerido</span>
        <h2>PBX Core no esta instalado en la base de datos</h2>
        <p>El modulo esta cargado en PHP, pero faltan tablas Realtime en MySQL. Ejecuta la migracion PBX Core y despues recarga esta pantalla.</p>
    </div>
    <span class="status-pill">PBX</span>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Tablas faltantes</span>
            <h3><?= count($missingTables) + count($missingColumns ?? []) ?> pendientes</h3>
        </div>
    </div>

    <div class="module-list">
        <?php foreach ($missingTables as $table): ?>
            <article class="module-row">
                <strong><?= e($table) ?></strong>
                <span class="badge">No existe</span>
            </article>
        <?php endforeach; ?>
        <?php foreach (($missingColumns ?? []) as $column): ?>
            <article class="module-row">
                <strong><?= e($column) ?></strong>
                <span class="badge">Columna faltante</span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Comando</span>
            <h3>Actualizar servidor</h3>
        </div>
    </div>
    <pre class="code-block">cd /var/www/uc200-core
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_core.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_secure_extension_auth.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_schema_repair.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_auth_username_identify.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_auth_realm_digest.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_aor_matches_endpoint.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_identify_by_auth_username.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_nat_direct_media.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_audio_defaults.sql
mysql -u uc200 -p uc200_core &lt; database/updates/2026_05_15_pbx_security_recordings.sql
mysql -u uc200 -p uc200_core &lt; database/seed.sql</pre>
</section>
