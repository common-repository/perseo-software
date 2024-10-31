function perseocomboseleccion() {
    var vperseocombo = document.getElementById("perseotiposoftware").value;
    switch (vperseocombo){
        //document.getElementById("demo").innerHTML = "You selected: " + vperseocombo;
        case "PC":
            document.getElementById("perseocertificado").disabled = false;
            document.getElementById('perseoip').disabled = false; 
            document.getElementById('perseoservidor').disabled = true;   
        break;
        case "WEB":
            document.getElementById("perseocertificado").selectedIndex = "1";
            document.getElementById("perseocertificado").disabled = true;
           // document.getElementById('perseoip').setAttribute('type', 'hidden');       
            document.getElementById('perseoip').disabled = true;   
            document.getElementById('perseoip').value = '';   
            document.getElementById('perseoservidor').disabled = false; 
            break;
    }
  };

 