function esperaAi(msg, tempo){//Função esperai
    return new Promise(resolve, reject){//a função retorna uma promessa com reject e resolve
        if(typeof msg !=== 'string'){// se algo der errado:
            console.log('falhei'); //dizemos que falhou
            reject(false); //Mandamos pro reject, que manda pro catch, que captura e trata o erro
            return;
        }
        setTimeOut(()=>{//se não, espere
            resolve('Passei');//resolva (manda o código pro then)
            return;//cessa
        }, tempo);//
    }
}

async function executa(){
    try{
        const fase1 = await esperaAi('f1', 1000);
        console.log(fase1);

        const fase2 = await esperaAi('f2', 1000);
        console.log(fase2);

        const fase3 = await esperaAi('f3', 1000);
        console.log(fase3);
    } catch(e){
        console.log(e);
    }
}