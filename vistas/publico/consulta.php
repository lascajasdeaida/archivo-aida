<?php
/**
 * Consulta a pedido (docs/ux/sitio-publico.md §3 y §5.2, maqueta consulta.html). Sin cuenta y sin captcha visual.
 * Errores en texto junto al campo + resumen arriba con enlaces (foco en el resumen). Anti-spam: campo trampa oculto,
 * tiempo mínimo firmado y CSRF por cookie (SitioPublico::recibirConsulta).
 * Recibe $d (ficha), $t (tarjeta), $tipo ('a_pedido' | 'original'), $errores, $valores, $aviso, $token, $instante, $enl.
 */
use Aida\Consultas;
use Aida\Web\Html as H;

$codigo = (string)$d['registro']['codigo'];
$original = $tipo === 'original';
$tituloPagina = ($original ? 'Consultar por un documento' : 'Pedir la consulta de un documento') . " ($codigo)";
$v = fn(string $k) => H::e($valores[$k] ?? '');
$err = function (string $k) use ($errores): string {
    return '<p class="error" id="' . $k . '-error"' . (isset($errores[$k]) ? '' : ' hidden') . '>'
        . (isset($errores[$k]) ? H::icono('alerta') . H::e($errores[$k]) : '') . '</p>';
};
$inv = fn(string $k) => isset($errores[$k]) ? ' aria-invalid="true"' : '';
$etiquetas = ['nombre' => 'Nombre y apellido', 'correo' => 'Correo electrónico', 'uso' => '¿Para qué lo querés consultar?',
              'motivo' => 'Mensaje', 'condiciones' => 'Condiciones de uso'];
$caja = $d['caja'];
ob_start(); ?>
<div class="contenedor">
  <nav class="migas" aria-label="Estás en"><ol>
    <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
    <li><a href="<?= H::e($enl->caja($caja)) ?>">Caja <?= (int)$caja['numero'] ?></a></li>
    <li><a href="<?= H::e($enl->registro($d['registro'])) ?>"><?= H::e($codigo) ?></a></li>
    <li aria-current="page">Consulta a pedido</li>
  </ol></nav>
  <h1 id="h-consulta"><?= $original ? 'Consultar por un documento' : 'Pedir la consulta de un documento' ?></h1>
  <?php if ($original): ?>
  <p>Este documento <?= $d['digital'] ? 'está' : 'todavía no está' ?> digitalizado. Si necesitás consultar el original o saber más, escribinos. No hace falta crear una cuenta.</p>
  <?php else: ?>
  <p>Algunos documentos del archivo se consultan a pedido: la ficha es pública y el documento lo comparte el equipo del archivo después de recibir tu pedido. No hace falta crear una cuenta.</p>
  <?php endif ?>
  <div class="pendiente-cliente" role="note" style="margin-top:16px"><b>Texto a definir por el cliente:</b> quién responde, por qué medio y en qué plazo; si la consulta es por copia digital, presencial o ambas.</div>

  <div class="consulta" style="margin-top:24px">
    <div class="consulta__doc"><h2 class="consulta__doc-titulo">Documento pedido</h2>
      <?php if ($t) { $nivel = 3; $etiqueta = 'div'; require __DIR__ . '/_tarjeta.php'; } ?></div>
    <div>
      <?php if ($errores): ?>
      <div class="resumen-errores" id="resumen-errores" role="alert">
        <h2 tabindex="-1" data-enfocar>Revisá <?= count($errores) === 1 ? 'un dato' : count($errores) . ' datos' ?> antes de enviar</h2>
        <ul><?php foreach ($errores as $k => $m): ?><li><a href="#<?= $k ?>"><?= H::e($m) ?></a></li><?php endforeach ?></ul>
      </div>
      <?php elseif ($aviso): ?>
      <div class="resumen-errores" id="resumen-errores" role="alert"><h2 tabindex="-1" data-enfocar><?= H::e($aviso) ?></h2></div>
      <?php endif ?>
      <form class="form" id="form-consulta" method="post" action="<?= H::e($enl->consulta($codigo, $original ? 'original' : null)) ?>" novalidate
            data-codigo="<?= H::e($codigo) ?>" data-caja="<?= (int)$caja['numero'] ?>">
        <input type="hidden" name="_t" value="<?= H::e($token) ?>">
        <input type="hidden" name="_i" value="<?= H::e($instante) ?>">
        <div class="trampa" aria-hidden="true"><label for="sitio_web">No completar este campo</label>
          <input id="sitio_web" name="sitio_web" type="text" tabindex="-1" autocomplete="off"></div>
        <p class="meta">Todos los campos son obligatorios, salvo los marcados como opcionales.</p>
        <div class="campo"><label for="nombre">Nombre y apellido</label>
          <input id="nombre" name="nombre" type="text" autocomplete="name" maxlength="120" value="<?= $v('nombre') ?>" aria-describedby="nombre-error"<?= $inv('nombre') ?> required><?= $err('nombre') ?></div>
        <div class="campo"><label for="correo">Correo electrónico</label>
          <span class="ayuda" id="correo-ayuda">Te respondemos a este correo.</span>
          <input id="correo" name="correo" type="email" autocomplete="email" maxlength="190" value="<?= $v('correo') ?>" aria-describedby="correo-ayuda correo-error"<?= $inv('correo') ?> required><?= $err('correo') ?></div>
        <div class="campo"><label for="institucion">Institución o pertenencia <span class="meta">(opcional)</span></label>
          <input id="institucion" name="institucion" type="text" autocomplete="organization" maxlength="160" value="<?= $v('institucion') ?>"></div>
        <div class="campo"><label for="uso">¿Para qué lo querés consultar?</label>
          <select id="uso" name="uso" aria-describedby="uso-error"<?= $inv('uso') ?> required><option value="">Elegí una opción</option>
            <?php foreach (Consultas::USOS as $k => $n): ?><option value="<?= $k ?>"<?= ($valores['uso'] ?? '') === $k ? ' selected' : '' ?>><?= H::e($n) ?></option><?php endforeach ?>
          </select><?= $err('uso') ?></div>
        <fieldset class="campo"><legend>¿Cómo preferís consultarlo?</legend>
          <?php foreach (Consultas::MODALIDADES as $k => $n): ?>
          <label class="check"><input type="radio" name="modalidad" value="<?= $k ?>"<?= ($valores['modalidad'] ?? 'digital') === $k ? ' checked' : '' ?>> <?= H::e($n) ?></label>
          <?php endforeach ?></fieldset>
        <div class="campo"><label for="motivo">Mensaje</label>
          <span class="ayuda" id="motivo-ayuda">Contanos qué buscás. Si te interesan otros documentos, anotá sus códigos (ej. C2R12).</span>
          <textarea id="motivo" name="motivo" rows="5" maxlength="4000" aria-describedby="motivo-ayuda motivo-error"<?= $inv('motivo') ?> required><?= $v('motivo') ?></textarea><?= $err('motivo') ?></div>
        <div class="campo"><label class="check"><input type="checkbox" id="condiciones" name="condiciones" value="1"<?= !empty($valores['condiciones']) ? ' checked' : '' ?> aria-describedby="condiciones-error"<?= $inv('condiciones') ?>>
          <span>Acepto las <a href="<?= H::e($enl->sobre('condiciones')) ?>">condiciones de uso del material</a>.</span></label><?= $err('condiciones') ?></div>
        <p class="meta">Usamos tus datos solo para responder este pedido.</p>
        <?= \Aida\Turnstile::widget('consulta') ?>
        <button class="boton boton--primario" type="submit"><?= H::icono('sobre') ?>Enviar pedido</button>
      </form>
    </div>
  </div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
