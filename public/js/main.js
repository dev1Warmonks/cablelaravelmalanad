	$(document).ready(function() {	
		dashboardClick();
	});
	
	$loading = $('#loadingDiv').hide();
		
	function dashboardClick()
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "dashboard.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Dashboard");
				$("#contentheader").text("Dashboard");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function allTransactionsClick()
	{
		$('#container').html("");
		
		$ts = '99999999999999999';		
		
		$("body").addClass("loading");
		$.ajax({
			url: "viewbkuptransactions.php",
			type: "POST",
			data: {'ts': $ts},
			success:function(result){ 
				$('#container').html(result);
				
				$("#currentlocation").text("Transactions");
				$("#contentheader").text("Transactions");
				$("body").removeClass("loading");
			}
		});
	}
	
	function callsettings()
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "settings.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function allSubscribers()
	{
		$('#container').html("");
		
		$id = '1';		
		
		$("body").addClass("loading");
	
		$.ajax({
			url: "view_allsubscribers.php",
			type: "POST",
			data: {'subscriberid': $id},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Transactions");
				$("#contentheader").text("Transactions");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function addExpenses()
	{
		$('#container').html("");
				
		$("body").addClass("loading");
	
		$.ajax({
			url: "view_addexpenses.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Expense");
				$("#contentheader").text("Expense");
				
				$("body").removeClass("loading");
			}
		});
	}
			
	function createArea()
	{
		$('#container').html("");
				
		$("body").addClass("loading");
		$.ajax({
			url: "view_createarea.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Area");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function createExpenseHead()
	{
		$('#container').html("");
				
		$("body").addClass("loading");
		$.ajax({
			url: "view_createexpensehead.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Expense Head");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function createNode()
	{
		$('#container').html("");
				
		$("body").addClass("loading");
		$.ajax({
			url: "view_createnode.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Node");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function createAgent()
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_createagent.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Agent");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function createPackage()
	{
		$('#container').html("");
				
		$("body").addClass("loading");
		$.ajax({
			url: "view_createpackage.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Package");
				$("#contentheader").text("Settings");
				
				$("body").removeClass("loading");
			}
		});
	}
	
	function createSubscriber()
	{
		$('#container').html("");
		
		$('body').addClass('loading');
		$.ajax({
			url: "view_createsubscriber.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Create Subscriber");
				$("#contentheader").text("Settings");
				$('body').removeClass('loading');
			}
		});
	}
	
	function makePayment()
	{
		$('#container').html("");
		
		$('body').addClass('loading');
		$.ajax({
			url: "view_makepayment.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Settings > Make Payment");
				$("#contentheader").text("Settings");
				$('body').removeClass('loading');
			}
		});
	}
	
	function generateBill()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "ws_generatebill.php",
			type: "POST",
			success:function(result){ 
				$('body').removeClass('loading');
				alertify.alert(result);
			}
		});
	}
	
	function agentPayment()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "view_agentpayment.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$('body').removeClass('loading');
			}
		});
	}
	
	function subscriberPayment()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "view_subscriberpayment.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$('body').removeClass('loading');
			}
		});
	}
	
	function adminWalletTxn()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "view_adminwallettxn.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$('body').removeClass('loading');
			}
		});
	}
	
	function agentWalletTxn()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "view_agentwallettxn.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$('body').removeClass('loading');
			}
		});
	}
	
	function myBillingHitory()
	{
		$('body').addClass('loading');
		$.ajax({
			url: "view_mybillinghistory.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$('body').removeClass('loading');
			}
		});
	}
	
	function dailyReportClick($from, $to)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "datewisereport.php",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function expenseReportClick($from, $to, $header)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "rpt_expensereport.php",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to, 'header': $header},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function discountReportClick($from, $to)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "rpt_discountreport.php",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function viewDateReport($fdate, $tdate)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_datereport.php",
			type: "POST",
			data : {'fdate' : $fdate, 'tdate' : $tdate},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function nonPaidCustomers($type, $amount)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_nonpaid.php",
			type: "POST",
			data : {'condition' : $type, 'amount' : $amount},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function agentWiseReport($agentid)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_agentwisereport.php",
			type: "POST",
			data : {'agentid' : $agentid},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function agentwiseCollectionClick($from, $to)
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "rpt_agentwisecollection.php",
			type: "POST",
			data : {'sfromdate' : $from, 'stodate' : $to},
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function malanadSubscription()
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_malanadsub.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Reporting");
				$("#contentheader").text("Reporting");
				$("body").removeClass("loading");
			}
		});	
	}
	
	function attendanceClick()
	{
		$('#container').html("");
		
		$("body").addClass("loading");
		$.ajax({
			url: "view_attendancecalendar.php",
			type: "POST",
			success:function(result){ 
				$('#container').html(result);
				$("#currentlocation").text("Attendance");
				$("#contentheader").text("Attendance");
				
				$("body").removeClass("loading");
			}
		});
	}