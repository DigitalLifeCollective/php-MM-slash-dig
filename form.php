<!DOCTYPE html>
<head>
  <meta charset="utf-8">

  <?php include('base.php'); ?>

  <title><?php echo MAIN_TITLE; ?> | Holonic Chart</title>


  <!-- jQuery -->
  <script src="js/jquery.min.js"></script>
  

  <script src="/assets/js/jsoneditor.min.js"></script>


</head>

<body>

<style>

@import url("https://fonts.googleapis.com/css?family=Ropa+Sans");
@import url("https://fonts.googleapis.com/css?family=Merriweather:900,900italic,300,300italic");

a, body, button, h1, h2, h3, h4, h5, h6, input, li, ol, p, select, span, textarea, ul {
    font-family: "Ropa Sans", Helvetica, sans-serif !important;
}

a, span { color: #00B0A0; }



/* form-control FIELDS */

input, select, textarea, option {
  font-size: 2rem !important;
  border: 1px solid #404040 !important;
  background: #fff  !important;
  width: 90% !important; 
  height: 60px;
  display: inline;
}

input:focus, option:focus, textarea:focus, select:focus {
    background-color: #E0E0E0 !important;
}

select {
    background: #fff url(/images/arrow_down.png) no-repeat 95% 50% !important;
}


label, .error { display: block; }

input[type="reset"], input[type="button"], button, p a.button {
    -moz-appearance: none;
    -webkit-appearance: none;
    -ms-appearance: none;
    appearance: none;
    -moz-transition: background-color 0.2s ease;
    -webkit-transition: background-color 0.2s ease;
    -ms-transition: background-color 0.2s ease;
    transition: background-color 0.2s ease;
    background-color: transparent;
    border: 1px solid #808080 !important;
    border-radius: 3em;
    color: #404040 !important;
    cursor: pointer;
    display: block-inline;
    font-family: "Ropa Sans", Helvetica, sans-serif !important;
    font-size: 1.2em !important;
    font-weight: bold !important;
    height: calc(3.75em + 2px);
    letter-spacing: 0.25em !important;
    line-height: 3.75em !important;
    outline: 0 !important;
    padding: 0 2.75em !important;
    margin: 1em !important;
    position: relative !important;
    text-align: center !important;
    text-decoration: none !important;
    text-trans.form-control: uppercase !important;
    white-space: nowrap !important;
}

@media only screen and (max-width: 768px) {
input[type="reset"], input[type="button"], button, p a.button { 
    height: calc(2.75em + 2px);
    letter-spacing: 0.25em;
    line-height: 2.75em;
    outline: 0;
    padding: 0 1.75em; }
}

input[type="submit"].highlight, input[type="reset"].highlight, input[type="button"].highlight, button.highlight, .button.highlight {
  background-color: #FEC019;
}


input[type="submit"].dark, input[type="reset"].dark, input[type="button"].dark, button.dark, .button.dark {
  background-color: #404040;
  color: white !important;
}

</style>


  <h1 id="title">JSON EDITOR</h1>

  <div id='editor_holder'></div>


  <button id='submit'>Submit (console.log)</button>
    
   <script>
      // Initialize the editor with a JSON schema
      var editor = new JSONEditor(document.getElementById('editor_holder'),{
        schema: {
          type: "object",
          title: "Car",
          properties: {
            make: {
              type: "string",
              enum: [
                "Toyota",
                "BMW",
                "Honda",
                "Ford",
                "Chevy",
                "VW"
              ]
            },
            model: {
              type: "string"
            },
            year: {
              type: "integer",
              enum: [
                1995,1996,1997,1998,1999,
                2000,2001,2002,2003,2004,
                2005,2006,2007,2008,2009,
                2010,2011,2012,2013,2014
              ],
              default: 2008
            }
          }
        }
      });
      
      // Hook up the submit button to log to the console
      document.getElementById('submit').addEventListener('click',function() {
        // Get the value from the editor
        console.log(editor.getValue());
      });
    </script>
</body>

</html>