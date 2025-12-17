<?php
// Configuração
$configFile = '/etc/smokeping/config.d/Targets';
$backupDir = '/var/backups/smokeping';

// Função para ler o arquivo e estruturar
function parseTargets($file) {
    if (!file_exists($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $tree = [];
    $currentSection = 'root';
    $buffer = [];
    
    foreach ($lines as $line) {
        if (trim($line) == '' || strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '++ ') === 0) {
            $name = trim(substr($line, 3));
            $tree[$currentSection]['hosts'][$name] = [];
            $buffer = &$tree[$currentSection]['hosts'][$name];
        } elseif (strpos($line, '+ ') === 0) {
            $name = trim(substr($line, 2));
            $currentSection = $name;
            $tree[$name] = ['conf' => [], 'hosts' => []];
            $buffer = &$tree[$name]['conf'];
        } elseif (strpos($line, '*** Targets ***') !== false) {
            continue;
        } else {
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $buffer[trim($k)] = trim($v);
            }
        }
    }
    return $tree;
}

// Salvar / Processar Formulários
if ($_POST) {
    // Backup automático antes de mexer
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    copy($configFile, $backupDir . '/Targets.' . time() . '.bak');

    $content = "*** Targets ***\n\nprobe = FPing\nmenu = Top\ntitle = Latência de Hosts\nremark = Painel Admin\n\n";
    $tree = parseTargets($configFile);

    // --- AÇÃO: Adicionar Grupo ---
    if ($_POST['action'] == 'add_group') {
        $tree[$_POST['name']] = [
            'conf' => ['menu' => $_POST['menu'], 'title' => $_POST['title']],
            'hosts' => []
        ];
    }
    // --- AÇÃO: Adicionar Host ---
    elseif ($_POST['action'] == 'add_host') {
        $tree[$_POST['group']]['hosts'][$_POST['name']] = [
            'menu' => $_POST['menu'],
            'title' => $_POST['title'],
            'host' => $_POST['ip']
        ];
    }
    // --- AÇÃO: Editar Host (NOVO) ---
    elseif ($_POST['action'] == 'edit_host') {
        $group = $_POST['group'];
        $oldName = $_POST['old_name']; // Nome antigo para localizar
        $newName = $_POST['name'];     // Novo nome (pode ser o mesmo)

        // Verifica se existe o host antigo
        if (isset($tree[$group]['hosts'][$oldName])) {
            // Remove o antigo (necessário se o nome mudou, inofensivo se for igual pois vamos recriar)
            unset($tree[$group]['hosts'][$oldName]);
            
            // Recria com os novos dados
            $tree[$group]['hosts'][$newName] = [
                'menu' => $_POST['menu'],
                'title' => $_POST['title'],
                'host' => $_POST['ip']
            ];
        }
    }
    // --- AÇÃO: Deletar ---
    elseif ($_POST['action'] == 'delete') {
        if ($_POST['type'] == 'group') unset($tree[$_POST['name']]);
        if ($_POST['type'] == 'host') unset($tree[$_POST['group']]['hosts'][$_POST['name']]);
    }

    // Reconstruir arquivo config
    foreach ($tree as $group => $data) {
        if ($group == 'root') continue; // Pula raiz interna
        $content .= "+ $group\n";
        foreach ($data['conf'] as $k => $v) $content .= "$k = $v\n";
        $content .= "\n";
        
        if (isset($data['hosts'])) {
            foreach ($data['hosts'] as $host => $conf) {
                $content .= "++ $host\n";
                foreach ($conf as $k => $v) $content .= "$k = $v\n";
                $content .= "\n";
            }
        }
    }

    file_put_contents($configFile, $content);
    // Recarrega o serviço
    exec('systemctl reload smokeping');
    
    // Redireciona para limpar o POST
    header("Location: admin.php");
    exit;
}

$tree = parseTargets($configFile);
?>

<!DOCTYPE html>
<html>
<head>
<title>SmokePing Admin</title>
<meta charset="UTF-8">
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; padding: 20px; color: #333; }
    .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; font-size: 1.2rem; color: #444; }
    h3 { margin: 0; font-size: 1rem; color: #2c3e50; }
    
    .group { margin-bottom: 15px; border: 1px solid #e1e4e8; padding: 15px; border-radius: 6px; background: #fff; }
    .host { 
        margin-left: 15px; padding: 8px 10px; margin-top: 5px;
        background: #f8f9fa; border-radius: 4px; border-left: 3px solid #2196F3;
        display: flex; justify-content: space-between; align-items: center;
    }
    
    button { cursor: pointer; border-radius: 4px; border: none; padding: 6px 12px; font-size: 0.9rem; transition: 0.2s; }
    button.del { background: #ffebee; color: #d32f2f; }
    button.del:hover { background: #ffcdd2; }
    button.edit { background: #e3f2fd; color: #1976D2; margin-right: 5px; }
    button.edit:hover { background: #bbdefb; }
    button.save { background: #4CAF50; color: white; width: 100%; padding: 10px; font-weight: bold; }
    button.save:hover { background: #43A047; }
    
    input, select { padding: 10px; margin: 5px 0 15px 0; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
    .row { display: flex; gap: 20px; flex-wrap: wrap; }
    
    /* Modal de Edição */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close:hover { color: black; }
</style>
</head>
<body>

<div class="row">
    <!-- Coluna da Esquerda: Adicionar -->
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <h2>➕ Novo Grupo</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_group">
                <label>ID do Grupo (sem espaços)</label>
                <input type="text" name="name" placeholder="ex: Clientes" required pattern="[A-Za-z0-9_]+">
                <label>Nome no Menu</label>
                <input type="text" name="menu" placeholder="ex: Clientes VIP" required>
                <label>Título da Página</label>
                <input type="text" name="title" placeholder="ex: Lista de Clientes VIP" required>
                <button type="submit" class="save">Criar Grupo</button>
            </form>
        </div>

        <div class="card">
            <h2>➕ Novo Host</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_host">
                <label>Grupo</label>
                <select name="group">
                    <?php foreach ($tree as $g => $d) if($g!='root') echo "<option value='$g'>$g</option>"; ?>
                </select>
                <label>ID do Host (sem espaços)</label>
                <input type="text" name="name" placeholder="ex: Cliente_Joao" required pattern="[A-Za-z0-9_]+">
                <label>Nome no Menu (Gráfico)</label>
                <input type="text" name="menu" placeholder="ex: João Silva" required>
                <label>Título do Gráfico</label>
                <input type="text" name="title" placeholder="ex: Conexão João" required>
                <label>IP / Hostname</label>
                <input type="text" name="ip" placeholder="192.168.0.10" required>
                <button type="submit" class="save" style="background:#2196F3;">Adicionar Host</button>
            </form>
        </div>
    </div>

    <!-- Coluna da Direita: Lista Atual -->
    <div style="flex: 2; min-width: 300px;">
        <div class="card">
            <h2>📜 Estrutura Atual</h2>
            <?php if (empty($tree) || count($tree) <= 1): ?>
                <p style="color:#666; text-align:center;">Nenhum grupo configurado ainda.</p>
            <?php endif; ?>

            <?php foreach ($tree as $group => $data): if($group=='root') continue; ?>
                <div class="group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div>
                            <h3>📁 <?php echo isset($data['conf']['menu']) ? $data['conf']['menu'] : $group; ?></h3>
                            <small style="color:#888;">ID: <?php echo $group; ?></small>
                        </div>
                        <form method="post" onsubmit="return confirm('ATENÇÃO: Deletar o grupo apaga todos os hosts dentro dele. Continuar?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type" value="group">
                            <input type="hidden" name="name" value="<?php echo $group; ?>">
                            <button class="del">🗑️ Apagar Grupo</button>
                        </form>
                    </div>
                    
                    <?php if (isset($data['hosts'])): foreach ($data['hosts'] as $host => $conf): ?>
                        <div class="host">
                            <div>
                                <span>🖥️ <b><?php echo isset($conf['menu']) ? $conf['menu'] : $host; ?></b></span><br>
                                <small style="color:#666;">IP: <?php echo isset($conf['host']) ? $conf['host'] : 'N/A'; ?></small>
                            </div>
                            <div style="display:flex;">
                                <!-- Botão Editar -->
                                <button type="button" class="edit" 
                                    onclick="openEditModal(
                                        '<?php echo $group; ?>', 
                                        '<?php echo $host; ?>', 
                                        '<?php echo isset($conf['menu']) ? addslashes($conf['menu']) : ''; ?>', 
                                        '<?php echo isset($conf['title']) ? addslashes($conf['title']) : ''; ?>', 
                                        '<?php echo isset($conf['host']) ? $conf['host'] : ''; ?>'
                                    )">✏️ Editar</button>

                                <!-- Botão Deletar -->
                                <form method="post" onsubmit="return confirm('Tem certeza que deseja remover este host?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="type" value="host">
                                    <input type="hidden" name="group" value="<?php echo $group; ?>">
                                    <input type="hidden" name="name" value="<?php echo $host; ?>">
                                    <button class="del">X</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>✏️ Editar Host</h2>
        <form method="post">
            <input type="hidden" name="action" value="edit_host">
            <input type="hidden" name="group" id="edit_group">
            <input type="hidden" name="old_name" id="edit_old_name">
            
            <label>ID do Host</label>
            <input type="text" name="name" id="edit_name" required pattern="[A-Za-z0-9_]+">
            
            <label>Nome no Menu</label>
            <input type="text" name="menu" id="edit_menu" required>
            
            <label>Título do Gráfico</label>
            <input type="text" name="title" id="edit_title" required>
            
            <label>IP</label>
            <input type="text" name="ip" id="edit_ip" required>
            
            <button type="submit" class="save">Salvar Alterações</button>
        </form>
    </div>
</div>

<script>
function openEditModal(group, name, menu, title, ip) {
    document.getElementById('edit_group').value = group;
    document.getElementById('edit_old_name').value = name; // Guarda o nome original
    document.getElementById('edit_name').value = name;     // Preenche campo editável
    document.getElementById('edit_menu').value = menu;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_ip').value = ip;
    
    document.getElementById('editModal').style.display = "block";
}

function closeEditModal() {
    document.getElementById('editModal').style.display = "none";
}

// Fechar modal se clicar fora
window.onclick = function(event) {
    var modal = document.getElementById('editModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

</body>
</html>
