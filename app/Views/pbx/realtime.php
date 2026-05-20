<section class="hero-panel">
    <div>
        <span class="eyebrow">Modo avanzado</span>
        <h2>Objetos internos del motor de comunicaciones</h2>
        <p>Vista tecnica para superadmin/engineer. Muestra identidades internas, autenticacion y registros del motor UC200.</p>
    </div>
</section>

<?php foreach ([['Endpoints', $endpoints], ['Auths', $auths], ['AORs', $aors]] as [$label, $rows]): ?>
    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Interno</span>
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
