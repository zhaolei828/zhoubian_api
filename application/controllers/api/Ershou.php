<?php
/**
 * Description of Ershou
 *
 * @author zhaolei
 */
require APPPATH.'/libraries/REST_Controller.php';

class Ershou extends REST_Controller {
    public function __construct() {
        parent::__construct();
    }
    
    function list_get()
    {
        $limit=1;
        if(!$this->get('es_type'))
        {
            $this->response("es_type is NULL", 400);
        }else {
            $es_type = $this->get('es_type');
        }
        if($this->get('limit'))
        {
            $limit = $this->get('limit');
        }
		
        $this->load->database();
        
        $this->db->select('ershou.id,title,description,old_price,now_price,goods_type,goods_type.name as goods_type_name,goods_type.icon as goods_type_icon,'
                . 'recency_info,recency_info.name as recency_info_name,change_flag,fb_user,fb_time,fb_zone,es_type');
        $this->db->from('ershou');
        $this->db->join('goods_type', 'ershou.goods_type = goods_type.id');
        $this->db->join('recency_info', 'ershou.recency_info = recency_info.id');
        $this->db->order_by("id", "desc");
        $where_array = array('es_type' => $es_type);
        if($this->get('pull_offset'))
        {
            $pull_offset = $this->get('pull_offset');
            if($pull_offset > 0){
                $where_array["ershou.id >"] = $pull_offset;
            }else if ($pull_offset < 0) {
                $where_array["ershou.id <"] = -$pull_offset;
            }
        }
        $query_es = $this->db->get_where('',$where_array, $limit);
        
        $list = $query_es->result();
        foreach ($list as $es_item) {
            $es_item_id = $es_item -> id;
            $query_attach = $this->db->get_where('attach', array('t' => 1,'t_id' => $es_item_id), NULL, NULL);
            $attach_list = $query_attach->result();
            $es_item -> attach_list = $attach_list;
        }
        $this->response($list, 200);
    }
    
    /**
     * 添加一条二手信息
     */
    function add_post(){
        $this->output->enable_profiler(TRUE);
        $base_url = $this->config->item('base_url');
        $user_id = $this->post('user_id');
        $title = $this->post('title');
        $description = $this->post('description');
        $old_price = $this->post('old_price');
        $now_price = $this->post('now_price');
        $goods_type = $this->post('goods_type');
        $recency_info = $this->post('recency_info');
        $change_flag = $this->post('change_flag');
        $es_type = $this->post('es_type');
        
        $today = date("Ymd");
        $path = 'uploads/'.$today;
        if( !file_exists($path) ) {
            mkdir($path);
        }
        $time = date("YmdHis");
        $config['upload_path'] = $path;
        $config['allowed_types'] = 'bmp|gif|jpeg|jpg|png';
        $config['max_size'] = '100';
        
        $attach_array = array();
        for($i=0;$i<5;$i++){
            $filename = 'userfile'.$i;
            @$name = $_FILES[$filename]['name'];
            if ( $name != '')
            {
                $config['file_name'] = $time.'f'.$i.rand(1000,9999);
                $this->load->library('upload', $config);
                if ( ! $this->upload->do_upload($filename))
                {
                    $this->response($error = array('error' => $this->upload->display_errors()), 500);
                } 
                else
                {
                    $data = $this->upload->data();
                    $attach = array(
                        'name' => $data['file_name'],
                        'path' => $data['full_path'],
                        'url' => $path.'/'.$data['file_name'],
                        'size' => $data['file_size'],
                        't' => 1,
                        't_id' => 0
                    );
                    array_push($attach_array,$attach);
                }
            }
            
        }
        
        $message = array(
            'id' => 0,
            'title' => $title,
            'description' => $description,
            'old_price' => $old_price,
            'now_price' => $now_price,
            'goods_type' => $goods_type,
            'recency_info' => $recency_info,
            'change_flag' => $change_flag,
            'attach_array' => $attach_array
        );
        
        $es_item = array(
            'title' => $title,
            'description' => $description,
            'old_price' => $old_price,
            'now_price' => $now_price,
            'goods_type' => $goods_type,
            'recency_info' => $recency_info,
            'change_flag' => $change_flag,
            'fb_time' => time(),
            'es_type' => $es_type
        );
        
        $this->load->database();
        $this->db->insert('ershou', $es_item);
        $es_item_id = $this->db->insert_id();
        $message['id'] = $es_item_id;
        foreach ($attach_array as $attach) {
            $attach['t_id'] = $es_item_id; 
            $this->db->insert('attach', $attach);
        }

        $this->response($message, 200); 
    }
}
?>
