<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class TablesAdminController
{
    private const PAGE = 'dizzy-reservation-tables';

    public function __construct(private TableRepository $tables) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_post_dizzy_save_table_layout', [$this, 'save']);
        add_action('admin_head', [$this, 'hideNotices']);
    }

    public function menu(): void
    {
        add_submenu_page('dizzy-reservations', __('Tables', 'dizzy-reservations-manager'), __('Tables', 'dizzy-reservations-manager'), 'manage_options', self::PAGE, [$this, 'render']);
    }

    public function hideNotices(): void
    {
        if (sanitize_key((string) ($_GET['page'] ?? '')) !== self::PAGE) return;
        echo '<style>#wpbody-content>.notice,#wpbody-content>.update-nag,#wpbody-content>div[class*="notice"],.wrap>.notice{display:none!important}</style>';
    }

    public function save(): void
    {
        if (! current_user_can('manage_options')) wp_die(esc_html__('Unauthorized', 'dizzy-reservations-manager'));
        check_admin_referer('dizzy_save_table_layout');
        $json = wp_unslash((string) ($_POST['tables_json'] ?? '[]'));
        $items = json_decode($json, true);
        if (is_array($items)) $this->tables->saveLayout($items);
        update_option('dizzy_reservation_floor_plan', esc_url_raw(wp_unslash((string) ($_POST['floor_plan'] ?? ''))));
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) wp_die(esc_html__('Unauthorized', 'dizzy-reservations-manager'));
        wp_enqueue_media();
        $rows = $this->tables->all();
        $background = (string) get_option('dizzy_reservation_floor_plan', DIZZY_RESERVATIONS_URL . 'assets/images/dizzy-seat-map.png');
        ?>
        <div class="wrap dizzy-tables-admin">
            <h1><?php esc_html_e('Tables', 'dizzy-reservations-manager'); ?></h1>
            <p><?php esc_html_e('Arrange tables on the floor plan. Drag a table to move it and select it to edit its details.', 'dizzy-reservations-manager'); ?></p>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success inline"><p><?php esc_html_e('Table layout saved.', 'dizzy-reservations-manager'); ?></p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dizzy-table-form">
                <input type="hidden" name="action" value="dizzy_save_table_layout"><?php wp_nonce_field('dizzy_save_table_layout'); ?>
                <input type="hidden" name="tables_json" id="dizzy-tables-json">
                <div class="dizzy-table-toolbar">
                    <input type="url" name="floor_plan" id="dizzy-floor-plan" value="<?php echo esc_attr($background); ?>">
                    <button type="button" class="button" id="dizzy-select-floor-plan"><?php esc_html_e('Select / Upload floor plan', 'dizzy-reservations-manager'); ?></button>
                    <button type="button" class="button" id="dizzy-auto-align"><?php esc_html_e('Auto-align Dizzy tables', 'dizzy-reservations-manager'); ?></button>
                    <button type="button" class="button" id="dizzy-add-table"><?php esc_html_e('Add table', 'dizzy-reservations-manager'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save layout', 'dizzy-reservations-manager'); ?></button>
                </div>
                <p class="description dizzy-table-keyboard-help"><?php esc_html_e('Select a table, then use the arrow keys to move it one pixel. Hold Shift for ten pixels.', 'dizzy-reservations-manager'); ?></p>
                <div class="dizzy-table-workspace">
                    <div class="dizzy-floor-plan" id="dizzy-floor-plan-stage" style="background-image:url('<?php echo esc_url($background); ?>')"></div>
                    <aside class="dizzy-table-editor" id="dizzy-table-editor">
                        <h2><?php esc_html_e('Table details', 'dizzy-reservations-manager'); ?></h2>
                        <p class="description" id="dizzy-no-table"><?php esc_html_e('Select a table on the plan.', 'dizzy-reservations-manager'); ?></p>
                        <div id="dizzy-table-fields" hidden>
                            <label><?php esc_html_e('Code', 'dizzy-reservations-manager'); ?><input type="text" data-key="code"></label>
                            <label><?php esc_html_e('Label', 'dizzy-reservations-manager'); ?><input type="text" data-key="label"></label>
                            <label><?php esc_html_e('Capacity', 'dizzy-reservations-manager'); ?><input type="number" min="1" max="100" data-key="capacity"></label>
                            <label><?php esc_html_e('Shape', 'dizzy-reservations-manager'); ?><select data-key="shape"><option value="square">Square</option><option value="round">Round</option><option value="rectangle">Rectangle</option></select></label>
                            <label><?php esc_html_e('Width %', 'dizzy-reservations-manager'); ?><input type="number" min="2" max="30" step=".1" data-key="width"></label>
                            <label><?php esc_html_e('Height %', 'dizzy-reservations-manager'); ?><input type="number" min="2" max="30" step=".1" data-key="height"></label>
                            <label><?php esc_html_e('Rotation', 'dizzy-reservations-manager'); ?><input type="number" min="-180" max="180" data-key="rotation"></label>
                            <label class="dizzy-active"><input type="checkbox" data-key="active"> <?php esc_html_e('Available for reservations', 'dizzy-reservations-manager'); ?></label>
                            <button type="button" class="button button-link-delete" id="dizzy-remove-table"><?php esc_html_e('Remove table', 'dizzy-reservations-manager'); ?></button>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
        <style>
            @font-face{font-family:"Dizzy Bebas Neue";src:url("<?php echo esc_url(DIZZY_RESERVATIONS_URL . 'assets/fonts/BebasNeue-Regular.otf'); ?>") format("opentype");font-style:normal;font-weight:400;font-display:swap}
            .dizzy-table-toolbar{display:flex;gap:8px;align-items:center;margin:15px 0 5px}.dizzy-table-keyboard-help{margin:0 0 15px}.dizzy-table-toolbar input{flex:1;min-width:220px}.dizzy-table-workspace{display:grid;grid-template-columns:minmax(500px,850px) 280px;gap:20px;align-items:start}.dizzy-floor-plan{position:relative;width:100%;aspect-ratio:1;background-color:#fff;background-size:100% 100%;background-repeat:no-repeat;border:1px solid #c3c4c7;overflow:hidden}.dizzy-layout-table{position:absolute;display:flex;align-items:center;justify-content:center;box-sizing:border-box;background:rgba(70,180,80,.78);color:#fff;font:400 18px/1 "Dizzy Bebas Neue",sans-serif;letter-spacing:.04em;cursor:move;user-select:none;touch-action:none}.dizzy-layout-table.round{border-radius:50%}.dizzy-layout-table.is-selected{background:#ffb900;box-shadow:0 0 0 3px rgba(255,185,0,.35)}.dizzy-layout-table.is-snapped{box-shadow:0 0 0 5px rgba(34,113,177,.42)}.dizzy-layout-table.is-disabled{filter:grayscale(1);opacity:.6}.dizzy-table-editor{background:#fff;border:1px solid #c3c4c7;padding:18px;position:sticky;top:45px}.dizzy-table-editor h2{margin-top:0}.dizzy-table-editor label{display:block;margin:0 0 13px;font-weight:600}.dizzy-table-editor label input:not([type=checkbox]),.dizzy-table-editor select{display:block;width:100%;margin-top:5px}.dizzy-table-editor .dizzy-active{font-weight:400}@media(max-width:1050px){.dizzy-table-workspace{grid-template-columns:1fr}.dizzy-table-editor{position:static}.dizzy-table-toolbar{align-items:stretch;flex-direction:column}}
        </style>
        <script>
        (()=>{
            const stage=document.getElementById('dizzy-floor-plan-stage'), form=document.getElementById('dizzy-table-form'), output=document.getElementById('dizzy-tables-json'), fields=document.getElementById('dizzy-table-fields'), empty=document.getElementById('dizzy-no-table'), floorPlan=document.getElementById('dizzy-floor-plan');
            const defaultPlan=<?php echo wp_json_encode(DIZZY_RESERVATIONS_URL . 'assets/images/dizzy-seat-map.png'); ?>;
            let items=<?php echo wp_json_encode(array_map(static fn(array $r): array => ['id'=>(int)$r['id'],'code'=>$r['code'],'label'=>$r['label'],'capacity'=>(int)$r['capacity'],'shape'=>$r['shape'],'x'=>(float)$r['pos_x'],'y'=>(float)$r['pos_y'],'width'=>(float)$r['width'],'height'=>(float)$r['height'],'rotation'=>(float)$r['rotation'],'active'=>(bool)$r['active']], $rows)); ?>, selected=-1;
            const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));
            const anchors={
                A8:[6.167,5,5.167,6.333],A7:[6.167,13.5,5.167,6.167],A6:[6.167,21.833,5.167,6.333],A5:[6.167,30.333,5.167,6.333],A4:[6.167,38.833,5.167,6.333],A3:[6.167,47.333,5.167,6.333],A2:[6.167,55.833,5.167,6.167],A1:[6.167,64.167,5.167,6.333],A0:[2,81.167,5,7.833],
                D1:[30.167,20.667,5.167,5.167],D2:[37.833,20.667,5.167,5.167],D3:[45.833,20.667,5.167,5.167],D4:[53.667,20.667,5.167,5.167],D5:[61.5,20.667,5.167,5.167],
                B3:[26,36.667,7.833,7.833],B2:[26,52.167,7.833,7.667],B1:[26,67.5,7.833,7.833],B0:[20.333,90,15.167,5.5],
                C2:[42.667,42.333,8.333,5.667],C3:[57.667,42.333,8.167,5.667],C1:[42.667,63.333,8.333,5.667],C4:[57.667,63.333,8.167,5.667],
                E1:[40.5,82.5,5.167,5.333],E2:[50,82.5,5.167,5.333],F1:[73.667,86.167,5.667,7.333],F2:[83.167,86.167,10.333,7.333]
            };
            const preset=item=>anchors[String(item.code).toUpperCase()]||null;
            function draw(){stage.innerHTML='';items.forEach((item,index)=>{const el=document.createElement('div');el.className='dizzy-layout-table '+item.shape+(index===selected?' is-selected':'')+(!item.active?' is-disabled':'');el.textContent=item.code;Object.assign(el.style,{left:item.x+'%',top:item.y+'%',width:item.width+'%',height:item.height+'%',transform:'rotate('+item.rotation+'deg)'});el.addEventListener('pointerdown',event=>{selected=index;edit();stage.querySelectorAll('.dizzy-layout-table').forEach(node=>node.classList.remove('is-selected'));el.classList.add('is-selected');const box=stage.getBoundingClientRect(),sx=event.clientX,sy=event.clientY,ox=item.x,oy=item.y;el.setPointerCapture(event.pointerId);el.onpointermove=e=>{let x=clamp(ox+(e.clientX-sx)/box.width*100,0,100-item.width),y=clamp(oy+(e.clientY-sy)/box.height*100,0,100-item.height),target=preset(item);el.classList.remove('is-snapped');if(target&&Math.hypot(x-target[0],y-target[1])<4){x=target[0];y=target[1];el.classList.add('is-snapped')}item.x=x;item.y=y;el.style.left=x+'%';el.style.top=y+'%';};el.onpointerup=()=>{el.onpointermove=null;};});stage.appendChild(el);});}
            function edit(){const item=items[selected];fields.hidden=!item;empty.hidden=!!item;if(!item)return;fields.querySelectorAll('[data-key]').forEach(input=>{const key=input.dataset.key;input.type==='checkbox'?input.checked=!!item[key]:input.value=item[key];});}
            fields.addEventListener('input',event=>{const input=event.target;if(!input.dataset.key||!items[selected])return;const key=input.dataset.key;items[selected][key]=input.type==='checkbox'?input.checked:(['capacity','width','height','rotation'].includes(key)?Number(input.value):input.value);draw();});
            document.getElementById('dizzy-add-table').onclick=()=>{items.push({id:0,code:'T'+(items.length+1),label:'Table '+(items.length+1),capacity:2,shape:'square',x:45,y:45,width:7,height:7,rotation:0,active:true});selected=items.length-1;edit();draw();};
            document.getElementById('dizzy-auto-align').onclick=()=>{if(!confirm('<?php echo esc_js(__('Load the supplied Dizzy floor plan and align all recognised A0–F2 tables to its exact table areas?', 'dizzy-reservations-manager')); ?>'))return;floorPlan.value=defaultPlan;stage.style.backgroundImage='url("'+defaultPlan.replace(/"/g,'')+'")';items.forEach(item=>{const target=preset(item);if(!target)return;[item.x,item.y,item.width,item.height]=target;item.rotation=0;});draw();};
            document.getElementById('dizzy-remove-table').onclick=()=>{if(selected<0)return;items.splice(selected,1);selected=-1;edit();draw();};
            document.getElementById('dizzy-select-floor-plan').onclick=()=>{const frame=wp.media({title:'Select floor plan',button:{text:'Use floor plan'},multiple:false});frame.on('select',()=>{const url=frame.state().get('selection').first().toJSON().url;document.getElementById('dizzy-floor-plan').value=url;stage.style.backgroundImage='url("'+url.replace(/"/g,'')+'")';});frame.open();};
            document.addEventListener('keydown',event=>{const directions={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]},direction=directions[event.key],target=event.target;if(!direction||selected<0||!items[selected]||target.matches('input, textarea, select, button, [contenteditable="true"]'))return;event.preventDefault();const item=items[selected],box=stage.getBoundingClientRect(),pixels=event.shiftKey?10:1;item.x=clamp(item.x+(direction[0]*pixels/box.width*100),0,100-item.width);item.y=clamp(item.y+(direction[1]*pixels/box.height*100),0,100-item.height);draw();edit();});
            form.addEventListener('submit',()=>{output.value=JSON.stringify(items);});draw();
        })();
        </script>
        <?php
    }
}
