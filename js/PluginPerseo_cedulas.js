function Perseovalidacioncedula() {
    //var cedula = '0931811087';
    var cedula = document.getElementById("PerseoIdentificacion").value;
    alert("Cedula no Validaaaaaaaaaaaaa");      
};

$(function(){
  
    /**
       * Algoritmo para validar cedulas de Ecuador
       * @Author : Victor Diaz De La Gasca.
       * @Fecha  : Quito, 15 de Marzo del 2013 
       * @Email  : vicmandlagasca@gmail.com
       * @Pasos  del algoritmo
       * 1.- Se debe validar que tenga 10 numeros
       * 2.- Se extrae los dos primero digitos de la izquierda y compruebo que existan las regiones
       * 3.- Extraigo el ultimo digito de la cedula
       * 4.- Extraigo Todos los pares y los sumo
       * 5.- Extraigo Los impares los multiplico x 2 si el numero resultante es mayor a 9 le restamos 9 al resultante
       * 6.- Extraigo el primer Digito de la suma (sumaPares + sumaImpares)
       * 7.- Conseguimos la decena inmediata del digito extraido del paso 6 (digito + 1) * 10
       * 8.- restamos la decena inmediata - suma / si la suma nos resulta 10, el decimo digito es cero
       * 9.- Paso 9 Comparamos el digito resultante con el ultimo digito de la cedula si son iguales todo OK sino existe error.     
       */
  
       var cedula = '0931811087';
  
       //Preguntamos si la cedula consta de 10 digitos
       if(cedula.length == 10){ var digito_region = cedula.substring(0,2); if( digito_region >= 1 && digito_region <=24 ){ var ultimo_digito   = cedula.substring(9,10); var pares = parseInt(cedula.substring(1,2)) + parseInt(cedula.substring(3,4)) + parseInt(cedula.substring(5,6)) + parseInt(cedula.substring(7,8));  var numero1 = cedula.substring(0,1);  var numero1 = (numero1 * 2); if( numero1 > 9 ){ var numero1 = (numero1 - 9); }  var numero3 = cedula.substring(2,3);  var numero3 = (numero3 * 2); if( numero3 > 9 ){ var numero3 = (numero3 - 9); } var numero5 = cedula.substring(4,5);  var numero5 = (numero5 * 2);  if( numero5 > 9 ){ var numero5 = (numero5 - 9); }  var numero7 = cedula.substring(6,7);  var numero7 = (numero7 * 2); if( numero7 > 9 ){ var numero7 = (numero7 - 9); } var numero9 = cedula.substring(8,9);  var numero9 = (numero9 * 2);  if( numero9 > 9 ){ var numero9 = (numero9 - 9); }  var impares = numero1 + numero3 + numero5 + numero7 + numero9;  var suma_total = (pares + impares); var primer_digito_suma = String(suma_total).substring(0,1);  var decena = (parseInt(primer_digito_suma) + 1)  * 10;  var digito_validador = decena - suma_total; if(digito_validador == 10) var digito_validador = 0; if(digito_validador == ultimo_digito){ console.log('la cedula:' + cedula + ' es correcta'); }else{  console.log('la cedula:' + cedula + ' es incorrecta');  } } else{ console.log('Esta cedula no pertenece a ninguna region'); } } else{ console.log('Esta cedula tiene menos de 10 Digitos'); }    
    
  });