<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\validarExistenciaTeam;
use DB;
use App\Team;
use App\Node;
use App\Playoff;
use Response;

class PlayoffController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function teams()
    {
        $teams = Team::all();
        $arr = [];
        foreach ($teams as $team) {
            $arr[] = ['nombre' => $team->teamname];
        }
        return Response::json($arr);
    }

    public function playoffs()
    {
        $user = auth('api')->user();

        $arr = [];
        $playoffs = $user->playoffs()->getResults();

        foreach ($playoffs as $playoff) {
            $playoffArr = ['id' => $playoff->id, 'arbol' => $playoff->playoff()];
            array_push($arr, $playoffArr);
        }

        return Response::json($arr);
    }

    public function store(Request $request)
    {
        $request->validate([
            'teams' => ['required', 'array', 'size:16'],
            'teams.*' => ['required', 'array', 'size:1'],
            'teams.*.*' => ['required', 'string', 'max:255', new validarExistenciaTeam()],
            'cuartos' => ['required', 'array', 'size:8'],
            'cuartos.*' => ['required', 'string', 'max:255', new validarExistenciaTeam()],
            'semis' => ['required', 'array', 'size:4'],
            'semis.*' => ['required', 'string', 'max:255', new validarExistenciaTeam()],
            'final' => ['required', 'array', 'size:2'],
            'final.*' => ['required', 'string', 'max:255', new validarExistenciaTeam()],
            'winner' => ['required', 'string', 'max:255', new validarExistenciaTeam()],
        ]);

        $data = $request->all();
        $user = auth('api')->user();

        try {
            DB::beginTransaction();
            $arbol = [];
            $nodo = Node::create([
                'name' => $data['winner'],
            ]);
            array_push($arbol, $nodo);
            foreach ($data['final'] as $final) {
                $nodo = Node::create([
                    'name' => $final,
                ]);
                array_push($arbol, $nodo);
            }
            foreach ($data['semis'] as $semi) {
                $nodo = Node::create([
                    'name' => $semi,
                ]);
                array_push($arbol, $nodo);
            }
            foreach ($data['cuartos'] as $cuarto) {
                $nodo = Node::create([
                    'name' => $cuarto,
                ]);
                array_push($arbol, $nodo);
            }
            foreach ($data['teams'] as $team) {
                $nodo = Node::create([
                    'name' => $team['nombre'],
                ]);
                array_push($arbol, $nodo);
            }

            $size = count($arbol);
            for ($i = 1; $i <= $size; $i++) {
                if ((($i * 2) + 1) <= $size) {
                    $padre = $arbol[$i - 1];
                    $hijoIzq = $arbol[($i * 2) - 1];
                    $hijoDer = $arbol[($i * 2)];

                    $padre['HI'] = $hijoIzq['id'];
                    $padre['HD'] = $hijoDer['id'];
                    $padre->save();
                }
            }

            $raiz = $arbol[0];
            Playoff::create([
                'user_id' => $user->id,
                'raiz' => $raiz['id'],
            ]);
            DB::commit();
            return response()->json($data,201);
        } catch (\Exception $e) {
            DB::rollback();
            abort(500);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $user = auth('api')->user();
            $playoff = Playoff::where('id',$id)->where('user_id',$user->id)->first();

            if($playoff==null){
                abort(403, 'Unauthorized action.');
            }

            $raiz = Node::where('id',$playoff->raiz)->first();

            $arbol = $raiz->getArbolEnArreglo();

            $playoff->delete();

            foreach($arbol as $nodo){
                $nodo->delete();
            }

            DB::commit();
            return response()->json('OK',200);
        } catch (\Exception $e) {
            DB::rollback();
            abort(500);
        }
    }
}
