<section class="hero-panel">
    <div>
        <span class="eyebrow">Asterisk Realtime</span>
        <h2>Objetos PJSIP</h2>
        <p>Vista de verificacion para `ps_endpoints`, `ps_auths` y `ps_aors`. No ejecuta llamadas ni WebRTC.</p>
    </div>
</section>

<?php foreach ([['Endpoints', $endpoints], ['Auths', $auths], ['AORs', $aors]] as [$label, $rows]): ?>
    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Realtime</span>
                <h3><?= e($label) ?></h3>
            </div>
            <span class="muted"><?= count($rows) ?> registros</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Estado</th>
                        <th>Actualizado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= e($row['id']) ?></strong></td>
                            <td><?= e((string) ($row['company_id'] ?? 'global')) ?></td>
                            <td><?= e($row['status'] ?? 'active') ?></td>
                            <td><?= e($row['updated_at'] ?? $row['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
