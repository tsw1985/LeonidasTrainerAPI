<?php

// web/index.php
require_once 'vendor/autoload.php';
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
$app = new Silex\Application();

//Labels for trainers
$REDIS_LABEL_CUSTOMER_GYM_TRAINER = "[CustomerGymTrainer]";
$REDIS_LABEL_TRAINER_ID = "[TrainerID]";
$REDIS_LABEL_TRAINER_ID_SHARED = "[TrainerIDShared]";
$REDIS_LABEL_TRAINER_SHARED = "[TrainerShared]";

//Labels for sport activity
$REDIS_LABEL_CUSTOMER_GYM_SPORT_ACTIVITY = "[CustomerGymSportActivity]";
$REDIS_LABEL_SPORT_ID_SHARED = "[SportIDShared]";

//Labels for diets
$REDIS_LABEL_CUSTOMER_GYM_DIET = "[CustomerGymDiet]";
$REDIS_LABEL_DIET_SHARED = "[DietShared]";
$REDIS_LABEL_DIET_ID = "[DietID]";
$REDIS_LABEL_DIET_ID_SHARED = "[DietIDShared]";

//Label for control customer
$REDIS_LABEL_ACTIVE = "ACTIVE";
$REDIS_LABEL_CUSTOMER_PASSWORD = "[CustomerGymPassword]";
$REDIS_LABEL_CUSTOMER_GYM_ID = "[CustomerGymID]";
$REDIS_LABEL_USER_GYM = "[USER_GYM]";


$servername = "localhost";
$username = "root";
$password = "shadow";
$dbname = "LeonidasTrainer";


$app->get('/hello/{name}', function ($name) use ($app) {
    return 'Hello '.$app->escape($name);
});

//Esta funcion devuelve los entrenamientos propios que tiene el usuario logueado
//en el movil , es decir , los entrenamientos que ha hecho este usuario
//devuelve una lista de ids : 1-3-4-5-4-3-5 ...
$app->get('/getusertrainer', function (Request $request) use ($app) {
    
    global $REDIS_LABEL_CUSTOMER_GYM_TRAINER;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    $client = new Predis\Client();
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $status = 200;
    
    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.$password);

    $value = "";

    //Si el usuario existe y tiene entrenamientos entonces vamos a descargarlos
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != ""){
        //Ahora vamos a obtener los entrenamientos de este usuario
        //Original
        //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
        $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.$password);
        $value = $trainerForCustomerGym;
    }
    else{
        $status = 403;
    }
    
    return new Response($value,$status);
    
});


//Esta funcion devuelve todos los IDS de los entrenamientos  COMPARTIDOS para este cliente
//en forma de JSON 
                    //{
                    //id: "1",
                    //description: "Hipertrofia 3 Dias Principiantes",
                    //shared_by: "gabrielgg85@gmail.com"
                    //},
$app->get('/getusertrainershared', function (Request $request) use ($app) {
    
    global $servername;
    global $username;
    global $password;
    global $dbname;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;

    $client = new Predis\Client();
    
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    
    $dni   = $conn->real_escape_string($request->get('dniUser'));
    $phone = $conn->real_escape_string($request->get('phoneUser'));
    $password = hash('sha512', $phone);
    
    $sql = "select shtt.trainer_id as trainer_id , user.username as shared_with , 
            ( select username from auth_user where id = sht.usergym_id ) as shared_by
            ,BackEnd_trainer.name as trainer_name ,
            sht.users_to_shared_gym_id as iduser_shared
            from BackEnd_sharedtrainer as sht 
            inner join BackEnd_sharedtrainer_trainer as
            shtt on sht.id =  shtt.sharedtrainer_id 
            inner join auth_user as user on user.id = sht.users_to_shared_gym_id
            inner join BackEnd_usergympassword on BackEnd_usergympassword.usergym_id = user.id
            inner join BackEnd_trainer on BackEnd_trainer.id = shtt.trainer_id
            where sht.users_to_shared_gym_id = 
            (  select userpass.usergym_id from BackEnd_usergympassword as userpass 
               inner join auth_user as u on u.id = userpass.usergym_id
               where 
               u.username = '".$dni."'
               AND u.is_active = '1'    
               AND
               userpass.password = '".$password."' 
            ) ";
    $result = $conn->query($sql);

                         
    $list_array = array();

    if ($result->num_rows > 0) {
        $client->set($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password , "ACTIVE" );
        // output data of each row
        while($row = $result->fetch_assoc()) {
            
            $trainer_to_redis = array ("id"           => $row["trainer_id"],
                                        "description" => $row["trainer_name"],
                                        "shared_by"   => $row["shared_by"]
                                      );
            array_push($list_array, $trainer_to_redis);
            
        }
    } 
    $conn->close();

    return new Response( json_encode($list_array) ,200);
});

//Esta funcion descarga los entrenamiento uno a uno por id para
//el propio usuario. Es decir , descarga el entrenamiento que ha hecho
//el usuario logueado
$app->get('/gettrainer', function (Request $request) use ($app) {

    global $REDIS_LABEL_CUSTOMER_GYM_TRAINER;
    global $REDIS_LABEL_TRAINER_ID;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    $client = new Predis\Client();
    $idTrainer = $request->get('idTrainer');
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $status = 200;

    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.$password);
    //"[CustomerGymTrainer]gabrielgg85@gmail.com2" "1-2"


    $value = "You have not credentials here";
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != "") {
        
        $id_trainer_list = split('-',$trainerForCustomerGym);
        
        if ( count($id_trainer_list) > 0){
            
            $founded = false;
        
            for ( $i = 0; $i < count($id_trainer_list); $i++){
                if ( $id_trainer_list[$i] == $idTrainer ){
                    $value = $client->get($REDIS_LABEL_TRAINER_ID.$idTrainer);
                    $status = 200;
                    $founded = true;
                    break;
                }
            }
            
            if ( !$founded){
                $status = 404; 
                $value = "You have not assigned this trainer";
            }
        }

    }    
    else{
        $status = 403;
    }
    
    return new Response($value  ,$status);
    
});


//Esta funcion descarga los entrenamiento COMPARTIDOS uno a uno por id
//REDIS -> [TrainerIDShared]pepe@pepe.commm@mm.com" "1-2-3-4"
$app->get('/gettrainershared', function (Request $request) use ($app) {

    global $REDIS_LABEL_CUSTOMER_GYM_TRAINER;
    global $REDIS_LABEL_TRAINER_ID;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    global $REDIS_LABEL_TRAINER_SHARED;
    global $REDIS_LABEL_TRAINER_ID_SHARED;
    
    $client = new Predis\Client();
    $idTrainer = $request->get('idTrainer');
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $sharedby = $request->get('sharedby');
    $status = 200;

    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    //REDIS -> [TrainerIDShared]pepe@pepe.commm@mm.com" "1-2-3-4"
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_TRAINER_ID_SHARED.$sharedby.$dni);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_TRAINER_ID_SHARED.$dni);

    $value = "You have not credentials here";
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != "") {
        
        $id_trainer_list = split('-',$trainerForCustomerGym);
        
        if ( count($id_trainer_list) > 0){
            
            $founded = false;
        
            for ( $i = 0; $i < count($id_trainer_list); $i++){
                if ( $id_trainer_list[$i] == $idTrainer ){
                    $value = $client->get($REDIS_LABEL_TRAINER_ID.$idTrainer);
                    $status = 200;
                    $founded = true;
                    break;
                }
            }
            
            if ( !$founded){
                $status = 404; 
                $value = "You have not assigned this trainer";
            }
        }

    }    
    else{
        $status = 403;
    }
    
    return new Response($value  ,$status);
    
});




// ------------ DIETAS --------------

//Esta funcion devuelve todos los IDS de las dietas
//de un usuario normal. Es decir , devuevle la lista de ids de las dietas
//que ha hecho el usuario logueado en el movil . Devuelve algo asi : 1-3-4-5-6
$app->get('/getuserdiets', function (Request $request) use ($app) {
    
    global $REDIS_LABEL_CUSTOMER_GYM_DIET;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    $client = new Predis\Client();
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $status = 200;
    
    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_DIET.$dni.$password);

    $value = "";

    //Si el usuario existe y tiene entrenamientos entonces vamos a descargarlos
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != ""){
        //Ahora vamos a obtener los entrenamientos de este usuario
        //Original
        //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
        $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_DIET.$dni.$password);
        $value = $trainerForCustomerGym;
    }
    else{
        $status = 403;
    }
    
    return new Response($value,$status);
    
    
    
    
});

//Esta funcion devuelve el JSON de dietas compartidas para este usuario
 //{
                    //id: "1",
                    //description: "Hipertrofia 3 Dias Principiantes",
                    //shared_by: "gabrielgg85@gmail.com"
                    //},

$app->get('/getuserdietsshared', function (Request $request) use ($app) {

    global $servername;
    global $username;
    global $password;
    global $dbname;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    
    $dni   = $conn->real_escape_string($request->get('dniUser'));
    $phone = $conn->real_escape_string($request->get('phoneUser'));
    $password = hash('sha512', $phone);
    
    $sql = "select shtt.diet_id as diet_id , 
                user.username as shared_with , 
                ( select username from auth_user where id = sht.usergym_id ) as shared_by ,
                BackEnd_diet.name as diet_name , 
                sht.users_to_shared_gym_id as iduser_shared 
                from BackEnd_sharedtrainer as sht 
                inner join BackEnd_sharedtrainer_diet as shtt on sht.id = shtt.sharedtrainer_id 
                inner join auth_user as user on user.id = sht.users_to_shared_gym_id 
                inner join BackEnd_usergympassword on BackEnd_usergympassword.usergym_id = user.id 
                inner join BackEnd_diet on BackEnd_diet.id = shtt.diet_id 
                where sht.users_to_shared_gym_id = 
                ( 
                        select userpass.usergym_id from BackEnd_usergympassword as userpass 
                    inner join auth_user as u on u.id = userpass.usergym_id 
                    where u.username = '".$dni."' 
                    AND u.is_active = '1' 
                    AND userpass.password = '".$password."' 
                )";
    $result = $conn->query($sql);

                         
    $list_array = array();

    if ($result->num_rows > 0) {
        // output data of each row
        while($row = $result->fetch_assoc()) {
            
            $trainer_to_redis = array ("id"           => $row["diet_id"],
                                        "description" => $row["diet_name"],
                                        "shared_by"   => $row["shared_by"]
                                      );
            array_push($list_array, $trainer_to_redis);
            
        }
    } 
    $conn->close();

    return new Response( json_encode($list_array) ,200);
    
});

//Esta funcion descarga las dietas por id , es decir , descarga una dieta
//pasandole un id , usuario y password
$app->get('/getdiet', function (Request $request) use ($app) {

    global $REDIS_LABEL_CUSTOMER_GYM_DIET;
    global $REDIS_LABEL_DIET_ID;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    $client = new Predis\Client();
    $idTrainer = $request->get('idDiet');
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $status = 200;

    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_DIET.$dni.$password);
    //"[CustomerGymTrainer]gabrielgg85@gmail.com2" "1-2"
    
 


    $value = "You have not credentials here";
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != "" ) {
        
        $id_trainer_list = split('-',$trainerForCustomerGym);
        
        if ( count($id_trainer_list) > 0){
            
            $founded = false;
        
            for ( $i = 0; $i < count($id_trainer_list); $i++){
                if ( $id_trainer_list[$i] == $idTrainer ){
                    $value = $client->get($REDIS_LABEL_DIET_ID.$idTrainer);
                    $status = 200;
                    $founded = true;
                    break;
                }
            }
            
            if ( !$founded){
                $status = 404; 
                $value = "You have not assigned this trainer";
            }
        }

    }    
    else{
        $status = 403;
    }
    
    return new Response($value  ,$status);
   
    
});


//Esta funcion descarga
$app->get('/getdietshared', function (Request $request) use ($app) {

    global $REDIS_LABEL_CUSTOMER_GYM_TRAINER;
    global $REDIS_LABEL_DIET_ID;
    global $REDIS_LABEL_CUSTOMER_GYM_ID;
    
    global $REDIS_LABEL_DIET_SHARED;
    global $REDIS_LABEL_DIET_ID_SHARED;
    
    $client = new Predis\Client();
    $idTrainer = $request->get('idDiet');
    $dni   = $request->get('dniUser');
    $phone = $request->get('phoneUser');
    $sharedby = $request->get('sharedby');
    $status = 200;

    //Original
    //$customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.'-'.$phone);
    //$trainerForCustomerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_TRAINER.$dni.'-'.$phone);
    
    $password = hash('sha512', $phone);
    
    //REDIS -> [TrainerIDShared]pepe@pepe.commm@mm.com" "1-2-3-4"
    $customerGym = $client->get($REDIS_LABEL_CUSTOMER_GYM_ID.$dni.$password);
    $trainerForCustomerGym = $client->get($REDIS_LABEL_DIET_ID_SHARED.$sharedby.$dni);
    
    //echo "CUSTOMER GYM ---------> ".$customerGym;
    //echo "TRAINER FOR CUSTOMER --->".$trainerForCustomerGym;
    
    

    $value = "You have not credentials here";
    if ( $customerGym == "ACTIVE" && $trainerForCustomerGym != "") {
        
        $id_trainer_list = split('-',$trainerForCustomerGym);
        
        if ( count($id_trainer_list) > 0){
            
            $founded = false;
        
            for ( $i = 0; $i < count($id_trainer_list); $i++){
                if ( $id_trainer_list[$i] == $idTrainer ){
                    $value = $client->get($REDIS_LABEL_DIET_ID.$idTrainer);
                    $status = 200;
                    $founded = true;
                    break;
                }
            }
            
            if ( !$founded){
                $status = 404; 
                $value = "You have not assigned this trainer";
            }
        }

    }    
    else{
        $status = 403;
    }
    
    return new Response($value  ,$status);
    
    
});








$app->run();

