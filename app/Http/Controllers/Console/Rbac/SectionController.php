<?php
/*
 * @Author: 李志刚
 * @CopyRight: 2020-2030 衡水山木枝技术服务有限公司
 * @Date: 2019-01-03 20:14:16
 * @Description: 部门管理
 * @LastEditors: 李志刚
 * @LastEditTime: 2021-02-26 09:24:36
 * @FilePath: /CoinCMF/app/Http/Controllers/Console/Rbac/SectionController.php
 */

namespace App\Http\Controllers\Console\Rbac;

use Validator;
use App\Models\Rbac\Admin;
use App\Models\Rbac\Section;
use Illuminate\Http\Request;
use App\Http\Controllers\Console\ResponseController;

class SectionController extends ResponseController
{
    /**
     * 部门列表
     * @return [type] [description]
     */
    public function getList(Request $req)
    {
        try {
            // 搜索关键字
            $key = $req->input('key','');
            $page = $req->input('page', 1);
            $size = $req->input('size', 10);
            $list = Section::where(function($q) use($key){
                    if ($key != '') {
                        $q->where('name','like','%'.$key.'%');
                    }
                })->limit($size)->offset(($page - 1) * $size)->orderBy('id','asc')->get();
            $count = Section::where(function ($q) use ($key) {
                if ($key != '') {
                    $q->where('name', 'like', '%' . $key . '%');
                }
            })->count();
            return $this->resData(200,'获取部门数据成功...', ['list' => $list, 'count' => $count]);
        } catch (\Throwable $e) {
            return $this->resData(500,'获取数据失败，请稍后再试！',[]);
        }
    }
    // 创建部门
    public function postCreate(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'name' => 'required|max:255',
                'status' => 'required|in:true,false',
            ]);
            $attrs = array(
                'name' => '名称',
                'status' => '状态',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(400,$validator->errors()->all()[0].'...');
            }
            $name = $req->input('name');
            $status = $req->input('status') == true ? 1 : 0;
            Section::create(['name'=>$name,'status'=>$status]);
            return $this->resData(200,'创建部门成功...');
        } catch (\Throwable $e) {
            return $this->resData(500,'创建部门失败，请稍后再试...');
        }
    }
    // 修改名称
    public function postEdit(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'section_id' => 'required|integer',
                'name' => 'required|max:255',
                'status' => 'required|in:true,false',
            ]);
            $attrs = array(
                'section_id' => '部门ID',
                'name' => '名称',
                'status' => '状态',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(400,$validator->errors()->all()[0].'...');
            }
            $name = $req->input('name');
            $status = $req->input('status') == 'true' ? 1 : 0;
            Section::where('id',$req->input('section_id'))->update(['name'=>$name,'status'=>$status]);
            return $this->resData(200,'更新部门名称成功...');
        } catch (\Throwable $e) {
            return $this->resData(500,'更新部门名称失败，请稍后再试...');
        }
    }
    // 修改状态
    public function postStatus(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'section_id' => 'required|integer',
                'status' => 'required|in:true,false',
            ]);
            $attrs = array(
                'section_id' => '部门ID',
                'status' => '状态',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(400,$validator->errors()->all()[0].'...');
            }
            $status = $req->input('status') == 'true' ? 1 : 0;
            Section::where('id',$req->input('section_id'))->update(['status'=>$status]);
            return $this->resData(200,'更新部门状态成功...');
        } catch (\Throwable $e) {
            return $this->resData(500,'更新部门状态失败，请稍后再试...');
        }
    }
    // 查看单条信息
    public function postDetail(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'section_id' => 'required|integer',
            ]);
            $attrs = array(
                'section_id' => '部门ID',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(400, $validator->errors()->all()[0] . '...');
            }
            $section = Section::findOrFail($req->input('section_id'));
            return $this->resData(200, '查询成功...', $section);
        } catch (\Throwable $e) {
            return $this->resData(500, '查询失败，请稍后再试...');
        }
    }
    // 删除部门
    public function postRemove(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'section_id' => 'required|array',
            ]);
            $attrs = array(
                'section_id' => '部门ID',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(400,$validator->errors()->all()[0].'...');
            }
            $id = $req->input('section_id',[]);
            // 查询下属用户
            if(is_null(Admin::whereIn('section_id',$id)->first()))
            {
                Section::whereIn('id',$id)->delete();
                return $this->resData(200,'删除部门成功...');
            }
            else
            {
                return $this->resData(403,'部门下有用户...');
            }
        } catch (\Throwable $e) {
            return $this->resData(500,'删除部门失败，请稍后再试...');
        }
    }
}
